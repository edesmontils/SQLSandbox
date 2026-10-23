<?php
require_once __DIR__.'/dossiers.php';

// Paramètre de la requête HTTP, s'il est une chaîne (les tableaux, comme base[]=x, sont ignorés)
function param($nom) {
	return (isset($_REQUEST[$nom]) && is_string($_REQUEST[$nom])) ? $_REQUEST[$nom] : null;
}

// Jeton anti-CSRF, généré par index.php et renvoyé par le JavaScript
function jeton_valide() {
	return isset($_SESSION['csrf'], $_REQUEST['csrf'])
		&& is_string($_REQUEST['csrf'])
		&& hash_equals($_SESSION['csrf'], $_REQUEST['csrf']);
}

function purge_db() {
		$liste = array();
		foreach($_SESSION as $c => $s) {
			if (substr($c,0,6)=='copie_') {
				if (file_exists($s)) unlink($s);
			}
		}	
}

// Nombre maximal de lignes affichées pour un résultat
define('MAX_LIGNES', 1000);
// Durée maximale (secondes) d'attente d'un verrou et d'exécution PHP
define('DELAI_MAX', 5);
// Requêtes SQL exécutées en même temps depuis une même adresse IP, et attente
// maximale (secondes) d'une place libre. Une salle de TP derrière un même routeur
// partage une adresse : les requêtes des élèves sont courtes, elles attendent
// au plus un instant ; seules des requêtes lentes en rafale sont refusées.
define('REQUETES_PAR_IP', 4);
define('ATTENTE_PLACE', 3);
// Longueur maximale (octets) d'une requête saisie
define('LONGUEUR_MAX_REQUETE', 64 * 1024);
// Taille maximale (octets) d'une copie de base, pour éviter de remplir le disque
define('TAILLE_MAX_BASE', 20 * 1024 * 1024);
// Mémoire maximale (octets) de SQLite, qui alloue hors du memory_limit de PHP
// (printf('%.*c', 500000000, 'x') occupait sinon 500 Mo)
define('MEMOIRE_MAX_SQLITE', 64 * 1024 * 1024);

// PRAGMA en lecture seule utiles pour consulter la structure des tables
$GLOBALS['PRAGMAS_AUTORISES'] = array('table_info', 'table_xinfo', 'index_list',
                                      'index_info', 'index_xinfo', 'foreign_key_list');

// Autorise ou refuse les actions SQL. ATTACH/DETACH (et donc VACUUM INTO)
// permettraient d'écrire des fichiers arbitraires sur le serveur, les autres PRAGMA
// de modifier le comportement de la base (dont la limite de taille), les objets
// temporaires de contourner cette limite. fts3_tokenizer() divulgue des adresses
// mémoire. Les autres refus ferment des failles connues des anciens SQLite (voir
// sqlite_recent()) : inutiles pour apprendre SQL, ils sont appliqués partout.
function autorisation_sql($action, $arg1 = null, $arg2 = null) {
	switch ($action) {
		case SQLite3::ATTACH:
		case SQLite3::DETACH:
		// Tables virtuelles (fts3, fts4...) : CVE-2020-13630, CVE-2020-13632, CVE-2020-13631
		case SQLite3::CREATE_VTABLE:
		// Les objets temporaires échappent à la limite de taille de la base
		case SQLite3::CREATE_TEMP_TABLE:
		case SQLite3::CREATE_TEMP_INDEX:
		case SQLite3::CREATE_TEMP_TRIGGER:
		case SQLite3::CREATE_TEMP_VIEW:
			return SQLite3::DENY;
		case SQLite3::PRAGMA:
			return in_array(strtolower((string)$arg1), $GLOBALS['PRAGMAS_AUTORISES'], true)
				? SQLite3::OK : SQLite3::DENY;
		// Statistiques de l'optimiseur : des valeurs forgées font planter SQLite avant
		// 3.30 (CVE-2019-16168). ANALYZE les écrit sans passer par ces contrôles. La
		// suppression reste permise : DROP TABLE efface les statistiques de la table
		// en passant par l'autorizer, et effacer ne permet pas de forger des valeurs.
		case SQLite3::INSERT:
		case SQLite3::UPDATE:
			return (stripos((string)$arg1, 'sqlite_stat') === 0) ? SQLite3::DENY : SQLite3::OK;
		case SQLite3::FUNCTION:
			$fonction = strtolower((string)$arg2);
			if ($fonction == 'fts3_tokenizer') return SQLite3::DENY;
			// Dépassement d'entier dans printf() avant 3.32.1 (CVE-2020-13434)
			if (!sqlite_recent() && in_array($fonction, array('printf', 'format'), true)) return SQLite3::DENY;
			return SQLite3::OK;
		default:
			return SQLite3::OK;
	}
}

// Découpe $sql en jetons, en ignorant espaces et commentaires : array('mot', 'SELECT'),
// array('chaine', ...) pour les chaînes et identifiants entre délimiteurs, array(';', ';')
// et array('car', '(') pour les autres caractères.
function jetons_sql($sql) {
	$jetons = array();
	$n = strlen($sql);
	$i = 0;
	while ($i < $n) {
		$c = $sql[$i];
		if ($c == "'" || $c == '"' || $c == '`' || $c == '[') {
			$ferme = ($c == '[') ? ']' : $c;
			$j = strpos($sql, $ferme, $i + 1);
			$jetons[] = array('chaine', substr($sql, $i, ($j === false ? $n : $j + 1) - $i));
			$i = ($j === false) ? $n : $j + 1;
		} else if ($c == '-' && $i + 1 < $n && $sql[$i + 1] == '-') {
			$j = strpos($sql, "\n", $i);
			$i = ($j === false) ? $n : $j + 1;
		} else if ($c == '/' && $i + 1 < $n && $sql[$i + 1] == '*') {
			$j = strpos($sql, '*/', $i + 2);
			$i = ($j === false) ? $n : $j + 2;
		} else if (ctype_alpha($c) || $c == '_') {
			$j = $i;
			while ($j < $n && (ctype_alnum($sql[$j]) || $sql[$j] == '_')) $j++;
			$jetons[] = array('mot', strtoupper(substr($sql, $i, $j - $i)));
			$i = $j;
		} else {
			if (!ctype_space($c)) $jetons[] = array($c == ';' ? ';' : 'car', $c);
			$i++;
		}
	}
	return $jetons;
}

// Indique si $sql contient autre chose qu'une seule instruction (SQLite n'exécute
// que la première). Tient compte des chaînes, identifiants, commentaires et
// des corps de triggers (BEGIN ... END), qui contiennent des ';'.
function plusieurs_instructions($sql) {
	$mots = array();   // premiers mots de l'instruction
	$trigger = false;  // dans le corps BEGIN ... END d'un CREATE TRIGGER
	$cas = 0;          // profondeur des CASE ... END dans un trigger
	$fin = false;      // un ';' terminant la première instruction a été vu
	foreach (jetons_sql($sql) as $jeton) {
		list($type, $valeur) = $jeton;
		if ($fin && $type != ';') return true;
		if ($type == 'mot') {
			if (count($mots) < 4) $mots[] = $valeur;
			if ($valeur == 'BEGIN' && $mots[0] == 'CREATE' && in_array('TRIGGER', $mots)) $trigger = true;
			else if ($trigger && $valeur == 'CASE') $cas++;
			else if ($trigger && $valeur == 'END') {
				if ($cas > 0) $cas--; else $trigger = false;
			}
		} else if ($type == ';' && !$trigger) {
			$fin = true;
		}
	}
	return false;
}

// Avant SQLite 3.32.3, plusieurs failles connues touchent les fonctions de fenêtrage
// (OVER, WINDOW : CVE-2019-19244, CVE-2020-11655, CVE-2020-13871...) et printf()
// (CVE-2020-13434). Elles sont refusées sur ces versions (hébergement OVH : 3.27).
define('VERSION_SQLITE_RECENTE', 3032003);

// Adresse d'un fichier statique (js, css) suivie de sa date de modification : le navigateur
// recharge le fichier dès qu'il change au lieu de servir une ancienne copie en cache.
function url_fichier($chemin) {
	$date = @filemtime(__DIR__.'/'.ltrim($chemin, './'));
	return $chemin.($date ? '?v='.$date : '');
}

function sqlite_recent($version = null) {
	if (is_null($version)) $version = SQLite3::version()['versionNumber'];
	return $version >= VERSION_SQLITE_RECENTE;
}

function utilise_fenetrage($sql) {
	$jetons = jetons_sql($sql);
	foreach ($jetons as $k => $jeton) {
		if ($jeton[0] != 'mot' || !in_array($jeton[1], array('OVER', 'WINDOW'), true)) continue;
		// OVER (...), OVER nom_de_fenêtre, WINDOW nom AS (...)
		$suivant = isset($jetons[$k + 1]) ? $jetons[$k + 1] : null;
		if (!$suivant) continue;
		if ($suivant[1] == '(') return true;
		// un mot-clé après OVER/WINDOW : c'est un nom de colonne ou de table (select over from t)
		if ($suivant[0] == 'mot' && !in_array($suivant[1], array('FROM', 'WHERE', 'AS', 'AND', 'OR',
		    'ORDER', 'GROUP', 'HAVING', 'LIMIT', 'UNION', 'EXCEPT', 'INTERSECT', 'JOIN', 'ON', 'IS',
		    'IN', 'NOT', 'LIKE', 'BETWEEN', 'ASC', 'DESC', 'SET', 'VALUES'), true)) return true;
	}
	return false;
}

// Ouvre la copie de session de la base $base (null si elle n'existe pas).
function ouvre_base($base) {
	if (!is_string($base) || !isset($_SESSION["copie_$base"])) return null;
	$fic = $_SESSION["copie_$base"];
	// La copie est faite à la première utilisation (index.php ne réserve qu'un fichier
	// vide), ou refaite si elle a été purgée (onglet principal fermé pendant un TP).
	if ((!is_file($fic) || filesize($fic) == 0) && isset($_SESSION['originaux'][$base])) {
		@copy($_SESSION['originaux'][$base], $fic);
		clearstatcache(true, $fic); // sinon filesize() renvoie encore la taille d'avant la copie
	}
	if (!is_file($fic) || filesize($fic) == 0) return null;
	// Une simple lecture ne change pas la date du fichier : sans cela, la purge des
	// copies de plus de $seuil secondes (index.php) supprimerait une copie encore utilisée.
	@touch($fic);
	try {
		$db = new SQLite3($fic, SQLITE3_OPEN_READWRITE);
	} catch (Exception $e) {
		return null;
	}
	$db->busyTimeout(DELAI_MAX * 1000);
	$db->enableExceptions(false);
	$db->exec('PRAGMA hard_heap_limit = '.MEMOIRE_MAX_SQLITE);
	// Limites fixées avant l'autorizer, qui interdit ensuite de les modifier
	$taille_page = (int)$db->querySingle('PRAGMA page_size');
	if ($taille_page > 0) $db->exec('PRAGMA max_page_count = '.(int)(TAILLE_MAX_BASE / $taille_page));
	// Clés étrangères vérifiées, comme dans les autres SGBD : SQLite ne le fait pas par
	// défaut, et l'étudiant ne peut pas l'activer lui-même (PRAGMA refusé)
	$db->exec('PRAGMA foreign_keys = ON');
	$db->setAuthorizer('autorisation_sql');
	return $db;
}

// Message d'erreur SQLite échappé, complété si la base a atteint sa taille maximale
function message_erreur($db) {
	$m = htmlspecialchars($db->lastErrorMsg()).' ('.$db->lastErrorCode().')';
	if ($db->lastErrorCode() == 13) // SQLITE_FULL
		$m .= ' : la base a atteint sa taille maximale ('.(TAILLE_MAX_BASE / 1024 / 1024).' Mo).';
	if (stripos($db->lastErrorMsg(), 'FOREIGN KEY constraint failed') !== false)
		$m .= '<br/>Une clé étrangère n\'est pas respectée : soit la ligne fait référence à une valeur '
		     .'absente de la table référencée, soit la ligne supprimée ou modifiée est encore '
		     .'référencée par une autre table. Le bouton « 🧩 Modèle » montre les clés de la base.';
	return $m;
}

// Affiche un message lisible si la requête est arrêtée par la limite de durée
function arret_requete() {
	$e = error_get_last();
	if ($e && $e['type'] == E_ERROR && strpos($e['message'], 'Maximum execution time') !== false) {
		echo "<script>result_type='ko';</script>"
		    ."<p>Requête interrompue : durée maximale d'exécution dépassée (".DELAI_MAX." s).</p>";
	}
}

// Réserve une place d'exécution pour l'adresse IP du client (verrous flock sur des
// fichiers verrou_* de DOSSIER_TMP). Le verrou est libéré par le système à la fin du processus,
// même s'il est tué par la limite de durée. Retourne false si aucune place ne se
// libère à temps.
function reserve_execution() {
	static $verrou = null;
	if (!is_null($verrou)) return true; // déjà réservée par ce processus
	if (is_null(DOSSIER_TMP)) return true; // pas de dossier de travail : pas de limite
	$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'cli';
	$prefixe = DOSSIER_TMP.'/verrou_'.md5($ip).'_';
	$fin = microtime(true) + ATTENTE_PLACE;
	do {
		for ($i = 0; $i < REQUETES_PAR_IP; $i++) {
			$f = @fopen($prefixe.$i, 'c');
			if ($f && flock($f, LOCK_EX | LOCK_NB)) {
				@touch($prefixe.$i);
				$verrou = $f;
				return true;
			}
			if ($f) fclose($f);
		}
		usleep(100000);
	} while (microtime(true) < $fin);
	return false;
}

// Exécute $requete sur la base $base et affiche le résultat.
// Retourne true en cas de succès, null sinon.
function get($requete,$base) {
	$db = ouvre_base($base);
	if (is_null($db)) {
		echo "<script>result_type='ko';</script><p>Base inconnue ou inaccessible. "
		    ."Rouvrez l'application depuis sa page d'accueil pour réinitialiser les bases.</p>";
		return null;
	}
	// La session n'est plus modifiée : on la libère pour que les autres onglets
	// de l'élève ne restent pas bloqués pendant l'exécution de la requête
	if (session_status() == PHP_SESSION_ACTIVE) session_write_close();
	if (!reserve_execution()) {
		echo "<script>result_type='ko';</script><p>Trop de requêtes en cours depuis "
		    ."votre adresse : patientez quelques secondes avant de réessayer.</p>";
		$db->close();
		return null;
	}
	set_time_limit(DELAI_MAX);
	static $arret_enregistre = false;
	if (!$arret_enregistre) {
		register_shutdown_function('arret_requete');
		$arret_enregistre = true;
	}
	if (strlen($requete) > LONGUEUR_MAX_REQUETE) {
		echo "<script>result_type='ko';</script><p>Requête trop longue (au plus "
		    .(LONGUEUR_MAX_REQUETE / 1024)." Ko).</p>";
		$db->close();
		return null;
	}
	if (!sqlite_recent() && utilise_fenetrage($requete)) {
		echo "<script>result_type='ko';</script><p>Les fonctions de fenêtrage "
		    ."(OVER, WINDOW) ne sont pas disponibles sur ce serveur : sa version de SQLite (".html(SQLite3::version()['versionString'])
		    .") a des failles connues dans ces fonctions.</p>";
		$db->close();
		return null;
	}
	if (plusieurs_instructions($requete)) {
		echo "<p><strong>Attention :</strong> seule la première instruction a été exécutée, la suite a été ignorée.</p>";
	}
	$ok = null;
	$result = @$db->query($requete);
	if ($result) {
		$nbcol = $result->numColumns();
		if ($nbcol == 0) {
			// Requête sans résultat tabulaire (INSERT, UPDATE, CREATE...) : déjà exécutée
			$nb = $db->changes();
			echo "<script>result_type='ok';</script><p>Requête exécutée ($nb ligne(s) modifiée(s))</p>";
		} else {
			echo '<table class="resultat"><thead><tr>';
			for ($i = 0; $i < $nbcol; $i++) {
				echo '<th>'.htmlspecialchars($result->columnName($i)).'</th>';
			}
			echo "</tr></thead>\n<tbody>\n";
			$nbtuples = 0;
			$tronque = false;
			while ($ligne = @$result->fetchArray(SQLITE3_NUM)) {
				if ($nbtuples >= MAX_LIGNES) { $tronque = true; break; }
				echo '<tr>';
				foreach ($ligne as $valeur) {
					echo '<td>'.htmlspecialchars((string)$valeur).'</td>';
				}
				echo "</tr>\n";
				$nbtuples++;
			}
			echo '</tbody></table>';
			if ($db->lastErrorCode() != 0 && $db->lastErrorCode() != 100 && $db->lastErrorCode() != 101) {
				echo '<script>result_type=\'ko\';</script><p>'.message_erreur($db).'</p>';
			} else {
				echo "<script>result_type='ok';</script><p> $nbtuples ligne(s) obtenue(s)"
				     .($tronque ? ' (affichage limité aux '.MAX_LIGNES.' premières lignes)' : '').'</p>';
				$ok = true;
			}
			$result->finalize();
		}
		if (is_null($ok) && $nbcol == 0) $ok = true;
	} else {
		echo '<p>Problème dans la requête</p>';
		echo "<script>result_type='ko';</script><p>".message_erreur($db)."</p>";
	}
	$db->close();
	return $ok;
}

// Modèle de la base $base, lu sur la copie de session (il suit donc les CREATE, ALTER et
// DROP de l'étudiant) : tables avec colonnes, clé primaire et clés étrangères, vues, et
// instructions SQL de création. Retourne null si la base est inconnue.
function modele_base($base) {
	$db = ouvre_base($base);
	if (is_null($db)) return null;
	$modele = array('tables' => array(), 'vues' => array(), 'sql' => array());
	$objets = $db->query("SELECT type, name, sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' "
	                    ."AND sql IS NOT NULL ORDER BY CASE type WHEN 'table' THEN 1 WHEN 'view' THEN 2 "
	                    ."WHEN 'index' THEN 3 ELSE 4 END, lower(name)");
	$liste = array();
	while ($objets && ($o = $objets->fetchArray(SQLITE3_ASSOC))) $liste[] = $o;
	foreach ($liste as $o) {
		$modele['sql'][] = trim($o['sql']).';';
		if ($o['type'] == 'view') $modele['vues'][] = $o['name'];
		if ($o['type'] != 'table') continue;
		$nom = '"'.str_replace('"', '""', $o['name']).'"';
		$colonnes = array();
		$r = $db->query("PRAGMA table_info($nom)");
		while ($r && ($c = $r->fetchArray(SQLITE3_ASSOC))) {
			$colonnes[] = array('nom' => $c['name'], 'type' => $c['type'],
			                    'non_nul' => (bool)$c['notnull'], 'pk' => (int)$c['pk']);
		}
		// Clés étrangères : une entrée par contrainte, colonnes dans l'ordre de la contrainte
		$cles = array();
		$r = $db->query("PRAGMA foreign_key_list($nom)");
		while ($r && ($f = $r->fetchArray(SQLITE3_ASSOC))) {
			$id = $f['id'];
			if (!isset($cles[$id])) $cles[$id] = array('colonnes' => array(), 'table' => $f['table'], 'references' => array());
			$cles[$id]['colonnes'][$f['seq']] = $f['from'];
			// Référence implicite (REFERENCES t sans colonne) : la clé primaire de t
			$cles[$id]['references'][$f['seq']] = $f['to'];
		}
		foreach ($cles as &$k) {
			ksort($k['colonnes']); ksort($k['references']);
			$k['colonnes'] = array_values($k['colonnes']);
			$k['references'] = array_values($k['references']);
		}
		unset($k);
		$modele['tables'][] = array('nom' => $o['name'], 'colonnes' => $colonnes, 'cles' => array_values($cles));
	}
	// Références implicites complétées par la clé primaire de la table visée
	$pk = array();
	foreach ($modele['tables'] as $t) {
		$cols = array_filter($t['colonnes'], function ($c) { return $c['pk'] > 0; });
		usort($cols, function ($a, $b) { return $a['pk'] - $b['pk']; });
		$pk[strtolower($t['nom'])] = array_map(function ($c) { return $c['nom']; }, $cols);
	}
	foreach ($modele['tables'] as &$t) foreach ($t['cles'] as &$k) {
		if (count(array_filter($k['references'], 'is_null')) > 0 && isset($pk[strtolower($k['table'])]))
			$k['references'] = $pk[strtolower($k['table'])];
	}
	unset($t, $k);
	$db->close();
	return $modele;
}

// Tables et vues de la copie de session de $base, ou de la base d'origine $fichier
// (lue sans la copier, pour la page d'accueil)
function get_tables($base, $fichier = null) {
	$requete = "SELECT name FROM sqlite_master WHERE type IN ('table','view') AND name NOT LIKE 'sqlite_%' ORDER BY lower(name)";
	$table = array();
	if (is_null($fichier)) {
		$db = ouvre_base($base);
	} else {
		try {
			$db = new SQLite3($fichier, SQLITE3_OPEN_READONLY);
		} catch (Exception $e) {
			$db = null;
		}
	}
	if (is_null($db)) {
		echo "<p>Base ".htmlspecialchars($base)." inconnue ou inaccessible</p>";
		return $table;
	}
	$result = $db->query($requete);
	if ($result) {
		while($ligne = $result->fetchArray(SQLITE3_NUM)) {
			$table[] = $ligne[0];
		}
	} else {
		echo "<p>Problème dans la lecture de la structure de la base ".htmlspecialchars($base)."</p>";
		echo "<p>".(int)$db->lastErrorCode()." <br/> ".htmlspecialchars($db->lastErrorMsg())."</p>";
	}
	$db->close();
	return $table;
}

function liste_base($base) {
	if (!is_string($base) || !isset($_SESSION["copie_$base"])) {
		echo "<p>Rubrique ou base inconnue.</p>";
		return;
	}
	$tables = get_tables($base);
	sommaire($tables);
	foreach($tables as $ta) {
		$nom = htmlspecialchars($ta, ENT_QUOTES);
		echo "<a id='$nom'></a><div class='post'>";
		echo '	<h2 class="title">'."Table $nom".'</h2>';
		echo '    <div class="story">';
		get('select * from "'.str_replace('"','""',$ta).'" order by 1',$base);
		echo "<p class='retour-sommaire'><a href='#matiere'>⬆️ Retour au sommaire</a></p>";
		echo '</div></div>';
	}
}

// Sommaire des tables d'une base
function sommaire($tables) {
	echo "<a id='matiere'></a><div class='post'><h2 class='title'>Sommaire</h2><div class='story'><ul class='sommaire'>";
	foreach($tables as $ta) {
		$nom = htmlspecialchars($ta, ENT_QUOTES);
		echo "<li><a class='pastille' href='#$nom'>$nom</a></li>";
	}
	echo "</ul></div></div>";
}

//======================================================
// Travaux pratiques et questions (fichiers config.xml des bases)
//======================================================

function html($texte) {
	return htmlspecialchars((string)$texte, ENT_QUOTES);
}

// Arguments d'un appel JavaScript placé dans un attribut HTML (onClick...)
function args_js() {
	return html(implode(',', array_map('json_encode', func_get_args())));
}

// Configuration (élément base_de_donnee) de la base $base de la session, ou null.
// Le chemin vient de la session (rempli par index.php), jamais de la requête.
function config_base($base) {
	if (!is_string($base) || !isset($_SESSION['configs'][$base])) return null;
	$conf = @simplexml_load_file($_SESSION['configs'][$base], 'SimpleXMLElement',
	                             LIBXML_DTDLOAD|LIBXML_DTDATTR|LIBXML_NOBLANKS|LIBXML_NOCDATA);
	return ($conf && $conf->getName() == 'base_de_donnee') ? $conf : null;
}

// Contenu HTML d'un élément de la configuration (description, aide...)
function contenu_xml($element) {
	$t = '';
	if ($element) foreach($element->children() as $c) $t .= $c->asXML();
	return $t;
}

// Identifiant (xml:id) d'une question
function id_question($q) {
	$a = $q->attributes('xml', true);
	return isset($a['id']) ? (string)$a['id'] : '';
}

function cherche_tp($conf, $nom) {
	if ($conf && isset($conf->{'liste-TP'}))
		foreach($conf->{'liste-TP'}->TP as $tp)
			if ((string)$tp['nom'] === $nom) return $tp;
	return null;
}

function cherche_question($conf, $id) {
	if ($conf && isset($conf->{'liste-questions'}))
		foreach($conf->{'liste-questions'}->children() as $q)
			if (id_question($q) === $id) return $q;
	return null;
}

// Questions d'un TP, dans l'ordre du TP
function questions_tp($conf, $tp) {
	$liste = array();
	foreach($tp->{'ref-question'} as $ref) {
		$q = cherche_question($conf, (string)$ref['question']);
		if ($q) $liste[] = $q;
	}
	return $liste;
}

// Thèmes d'une question : nom => difficulté (attribut poids du DTD)
function themes_question($q) {
	$themes = array();
	if (isset($q->theme))
		foreach($q->theme->children() as $t) $themes[$t->getName()] = (int)$t['poids'];
	return $themes;
}

// Thèmes des questions ouvertes au travail thématique (disponibilité "ouvert")
function themes_ouverts($conf) {
	$themes = array();
	if ($conf && isset($conf->{'liste-questions'}))
		foreach($conf->{'liste-questions'}->children() as $q)
			if ((string)$q['disponibilité'] == 'ouvert') $themes += themes_question($q);
	return $themes;
}

function nom_theme($theme) {
	return ucfirst(str_replace('-', ' ', $theme));
}

// Texte SQL d'une requête décrite par sa structure (élément SQL du DTD)
function texte_sql($sql) {
	if (!$sql) return '';
	if (isset($sql->Text)) return trim((string)$sql->Text);
	$t = 'SELECT '.((string)$sql->Select['Distinct'] == 'Vrai' ? 'DISTINCT ' : '').trim((string)$sql->Select);
	$t .= "\nFROM ";
	if (isset($sql->From)) foreach($sql->From->children() as $c) $t .= texte_jointure($c);
	foreach(array('Where' => 'WHERE', 'Group-by' => 'GROUP BY', 'Having' => 'HAVING', 'Order-by' => 'ORDER BY') as $e => $mot)
		if (isset($sql->$e) && trim((string)$sql->$e) !== '') $t .= "\n$mot ".trim((string)$sql->$e);
	return $t;
}

function texte_jointure($e) {
	if ($e->getName() == 'Table') return trim((string)$e);
	$parties = array();
	foreach($e->children() as $c) if ($c->getName() != 'On') $parties[] = texte_jointure($c);
	if ($e->getName() == 'Natural-Join') return implode(' NATURAL JOIN ', $parties);
	return implode(' JOIN ', $parties).' ON '.trim((string)$e->On);
}

// Description et référence d'une base (contenu du fichier de configuration)
function description_base($base) {
	$conf = config_base($base);
	echo "<div class='post'><h2 class='title'>Base ".html($base)."</h2><div class='story'>";
	if ($conf) {
		echo contenu_xml($conf->description);
		$ref = trim((string)$conf->référence);
		if ($ref !== '') echo "<p>Référence : <a href='".html($ref)."'>".html($ref)."</a></p>";
	}
	echo "</div></div>";
}

// Liste des TP d'une base
function liste_TP($base) {
	$conf = config_base($base);
	echo "<div class='post'><h2 class='title'>Travaux pratiques</h2><ul>";
	$nb = 0;
	if ($conf && isset($conf->{'liste-TP'})) {
		foreach($conf->{'liste-TP'}->TP as $tp) {
			$nb++;
			echo "<li><a href='#' onClick='page_tp(".args_js($base, (string)$tp['nom']).");return false;'>"
			    .html($tp['nom'])."</a></li>";
		}
	}
	echo "</ul>";
	if ($nb == 0) echo "<h3 class='indicateur'>Aucun TP disponible actuellement</h3>";
	echo "</div>";
}

// Page d'accueil d'un TP
function page_tp($base, $nom_tp) {
	$conf = config_base($base);
	$tp = cherche_tp($conf, (string)$nom_tp);
	if (is_null($tp)) {
		echo "<p>TP inconnu.</p>";
		return;
	}
	$questions = questions_tp($conf, $tp);
	$themes = array();
	foreach($questions as $q) $themes += themes_question($q);

	echo "<div class='post'><h2 class='title'>".html($tp['nom'])."</h2>";
	echo contenu_xml($tp->{'objectif-pédagogique'});
	echo "<p class='actions'><a class='btn' href='tp.php?base=".html(urlencode($base))."&amp;tp=".html(urlencode($tp['nom']))."' target='_blank'>🚀 Commencer le TP</a></p>";
	echo "</div>";
	echo "<div class='post'><h2 class='title'>Nombre de questions : ".count($questions)."</h2></div>";
	echo "<div class='post'><h2 class='title'>Mots clés</h2><h3 class='indicateur'>";
	foreach(array_keys($themes) as $t) echo html(nom_theme($t))."<br/>";
	echo "</h3></div>";
	echo "<div class='post'><h2 class='title'>Déroulement</h2><ul>"
	    ."<li>Pour débuter le TP, appuyez sur « 🚀 Commencer le TP » : la liste des questions s'ouvre dans un nouvel onglet.</li>"
	    ."<li>Pour traiter une question, cliquez sur « ✏️ Répondre ».</li>"
	    ."<li>Dans une question :<ul>"
	    ."<li>« ▶️ Exécuter » (ou Ctrl+Entrée) lance votre requête (sauf pour les questions où il faut trouver l'intention) ;</li>"
	    ."<li>« 🧺 Mémoriser la réponse » garde votre réponse ;</li>"
	    ."<li>« ⬅️ Retour aux questions » revient à la liste, en mémorisant votre réponse.</li>"
	    ."</ul></li></ul>"
	    ."<p><a href='aides.php' target='_blank'>❓ Aide de l'application</a></p></div>";
}

// Thèmes disponibles pour le travail thématique d'une base, avec leur difficulté
function themes_dispo($base) {
	$themes = themes_ouverts(config_base($base));
	echo "<div class='post'><h2 class='title'>Questions à thèmes</h2><ul>";
	foreach($themes as $t => $poids) {
		echo "<li><a href='questionsThem.php?base=".html(urlencode($base))."&amp;theme=".html(urlencode($t))."' target='_blank'>"
		    .html(nom_theme($t))."</a></li>";
	}
	echo "</ul>";
	if (count($themes) == 0) {
		echo "<h3 class='indicateur'>Aucune question n'est disponible actuellement</h3>";
	} else {
		echo "<p><b><u>Difficulté des questions proposées</u></b><br/>";
		foreach($themes as $t => $poids) echo html(nom_theme($t))." : $poids/6<br/>";
		echo "</p>";
	}
	echo "</div>";
}

// Bouton d'ouverture d'une question (données passées à ouvre_question() en JavaScript)
function bouton_question($q, $base, $numero) {
	$sql = $q->SQL;
	$question = array(
		'base' => (string)$base,
		'numero' => $numero,
		'type' => $q->getName(),
		'intention' => trim((string)$q->intention),
		// Pour une requête à trous, le second élément SQL est la requête à compléter
		'sql' => texte_sql($q->getName() == 'rq-trou' && count($sql) > 1 ? $sql[1] : $sql[0]),
		'aide' => contenu_xml($q->aide)
	);
	echo "<p class='question'><span>Question $numero</span> ";
	echo "<button type='button' class='btn petit' title='Répondre à la question' "
	    ."onclick='ouvre_question(".args_js($question).")'>✏️ Répondre</button></p>";
}

// Liste des questions d'un TP (page tp.php)
function liste_question($base, $nom_tp) {
	$conf = config_base($base);
	$tp = cherche_tp($conf, (string)$nom_tp);
	if (is_null($tp)) {
		echo "<p>TP inconnu.</p>";
		return;
	}
	description_base($base);
	echo "<div class='post'><h2 class='title'>Questions du TP ".html($tp['nom'])."</h2>";
	if ((string)$tp['statut'] == 'note') {
		// TP noté : compte à rebours d'une heure
		echo "<h3 class='indicateur'>Temps restant : <span id='countdown'></span></h3>";
		echo "<script>
			var temps_restant = 60 * 60;
			function compte_a_rebours() {
				var s = temps_restant % 60;
				$('countdown').textContent = (Math.floor(temps_restant / 60) + ' min ' + (s < 10 ? '0' : '') + s + ' s');
				if (temps_restant > 0) temps_restant--;
			}
			compte_a_rebours();
			setInterval(compte_a_rebours, 1000);
		</script>";
	}
	$questions = questions_tp($conf, $tp);
	foreach($questions as $i => $q) bouton_question($q, $base, $i + 1);
	if (count($questions) == 0) echo "<h3 class='indicateur'>Aucune question n'est disponible actuellement</h3>";
	echo "</div>";
}

// Liste des questions ouvertes d'un thème (page questionsThem.php)
function liste_question_thema($base, $theme) {
	$conf = config_base($base);
	$themes = themes_ouverts($conf);
	if (!is_string($theme) || !isset($themes[$theme])) {
		echo "<p>Thème inconnu.</p>";
		return;
	}
	description_base($base);
	echo "<div class='post'><h2 class='title'>Thème : ".html(nom_theme($theme))."</h2>";
	$i = 0;
	foreach($conf->{'liste-questions'}->children() as $q)
		if ((string)$q['disponibilité'] == 'ouvert' && isset(themes_question($q)[$theme]))
			bouton_question($q, $base, ++$i);
	echo "</div>";
}

// En-tête commun des pages ouvertes dans un nouvel onglet (TP, thèmes, tables, aide)
function debut_page() {
	?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SQL Sandbox</title>
  <link rel="icon" href="images/favicon.svg" type="image/svg+xml">
  <link href="<?php echo url_fichier("js/codemirror/codemirror.css");?>" rel="stylesheet">
  <link href="<?php echo url_fichier("default.css");?>" rel="stylesheet">
  <script>
    csrf_token = <?php echo json_encode(isset($_SESSION['csrf']) ? $_SESSION['csrf'] : ''); ?>;
  </script>
  <!-- Éditeur SQL : CodeMirror 5 (licence MIT, js/codemirror/LICENSE) -->
  <script src="<?php echo url_fichier("./js/codemirror/codemirror.js");?>"></script>
  <script src="<?php echo url_fichier("./js/codemirror/sql.js");?>"></script>
  <script src="<?php echo url_fichier("./js/coloration.js");?>"></script>
  <script src="<?php echo url_fichier("./js/bd_base.js");?>"></script>
  <script src="<?php echo url_fichier("./js/modele.js");?>"></script>
</head>
<body>
<div class="container">
<header class="entete compacte">
  <div class="marque">
    <img src="images/logo.svg" alt="">
    <h1>SQL Sandbox</h1>
  </div>
</header>
<main id="contenu">
  <div id="posts">
<?php
}

function fin_page() {
	echo "  </div>\n</main>\n"
	    ."<footer class='pied'><p>© 2010-2026 E. Desmontils, Nantes Université"
	    ."<span class='separateur'>·</span> Logiciel libre sous licence GPL v3</p></footer>\n"
	    ."</div>\n</body>\n</html>\n";
}

// Vérifie que la session a été initialisée par index.php (copies des bases)
function session_initialisee() {
	if (isset($_SESSION['debug'])) return true;
	echo "<div class='post'><h2 class='title'>Session expirée ou absente</h2><div class='story'>"
	    ."<p class='message'>Le serveur ne reconnaît pas votre session : l'application n'a pas encore été "
	    ."ouverte dans ce navigateur, ou la session a expiré (elle dure 3 heures).</p>"
	    ."<p>Ouvrez l'application depuis sa page d'accueil, puis rouvrez cette page (TP, thème ou tables).</p>"
	    ."<p class='actions'><a class='btn' href='index.php'>🏠 Ouvrir l'application</a></p></div></div>";
	return false;
}
?>