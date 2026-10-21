<?php

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
define('DELAI_MAX', 10);
// Taille maximale (octets) d'une copie de base, pour éviter de remplir le disque
define('TAILLE_MAX_BASE', 20 * 1024 * 1024);

// PRAGMA en lecture seule utiles pour consulter la structure des tables
$GLOBALS['PRAGMAS_AUTORISES'] = array('table_info', 'table_xinfo', 'index_list',
                                      'index_info', 'index_xinfo', 'foreign_key_list');

// Autorise ou refuse les actions SQL. ATTACH/DETACH (et donc VACUUM INTO)
// permettraient d'écrire des fichiers arbitraires sur le serveur, les autres PRAGMA
// de modifier le comportement de la base (dont la limite de taille) et
// fts3_tokenizer() divulgue des adresses mémoire.
function autorisation_sql($action, $arg1 = null, $arg2 = null) {
	switch ($action) {
		case SQLite3::ATTACH:
		case SQLite3::DETACH:
			return SQLite3::DENY;
		case SQLite3::PRAGMA:
			return in_array(strtolower((string)$arg1), $GLOBALS['PRAGMAS_AUTORISES'], true)
				? SQLite3::OK : SQLite3::DENY;
		case SQLite3::FUNCTION:
			return strtolower((string)$arg2) == 'fts3_tokenizer' ? SQLite3::DENY : SQLite3::OK;
		default:
			return SQLite3::OK;
	}
}

// Indique si $sql contient autre chose qu'une seule instruction (SQLite n'exécute
// que la première). Tient compte des chaînes, identifiants, commentaires et
// des corps de triggers (BEGIN ... END), qui contiennent des ';'.
function plusieurs_instructions($sql) {
	$n = strlen($sql);
	$i = 0;
	$mots = array();   // premiers mots de l'instruction
	$trigger = false;  // dans le corps BEGIN ... END d'un CREATE TRIGGER
	$cas = 0;          // profondeur des CASE ... END dans un trigger
	$fin = false;      // un ';' terminant la première instruction a été vu
	while ($i < $n) {
		$c = $sql[$i];
		if ($c == "'" || $c == '"' || $c == '`' || $c == '[') {
			if ($fin) return true;
			$ferme = ($c == '[') ? ']' : $c;
			$j = strpos($sql, $ferme, $i + 1);
			$i = ($j === false) ? $n : $j + 1;
		} else if ($c == '-' && $i + 1 < $n && $sql[$i + 1] == '-') {
			$j = strpos($sql, "\n", $i);
			$i = ($j === false) ? $n : $j + 1;
		} else if ($c == '/' && $i + 1 < $n && $sql[$i + 1] == '*') {
			$j = strpos($sql, '*/', $i + 2);
			$i = ($j === false) ? $n : $j + 2;
		} else if (ctype_alpha($c) || $c == '_') {
			if ($fin) return true;
			$j = $i;
			while ($j < $n && (ctype_alnum($sql[$j]) || $sql[$j] == '_')) $j++;
			$mot = strtoupper(substr($sql, $i, $j - $i));
			if (count($mots) < 4) $mots[] = $mot;
			if ($mot == 'BEGIN' && $mots[0] == 'CREATE' && in_array('TRIGGER', $mots)) $trigger = true;
			else if ($trigger && $mot == 'CASE') $cas++;
			else if ($trigger && $mot == 'END') {
				if ($cas > 0) $cas--; else $trigger = false;
			}
			$i = $j;
		} else if ($c == ';') {
			if (!$trigger) $fin = true;
			$i++;
		} else {
			if ($fin && !ctype_space($c)) return true;
			$i++;
		}
	}
	return false;
}

// Ouvre la copie de session de la base $base (null si elle n'existe pas).
function ouvre_base($base) {
	if (!is_string($base) || !isset($_SESSION["copie_$base"])) return null;
	$fic = $_SESSION["copie_$base"];
	if (!is_file($fic)) return null;
	try {
		$db = new SQLite3($fic, SQLITE3_OPEN_READWRITE);
	} catch (Exception $e) {
		return null;
	}
	$db->busyTimeout(DELAI_MAX * 1000);
	$db->enableExceptions(false);
	// Limite de taille, fixée avant l'autorizer qui interdit ensuite de la modifier
	$taille_page = (int)$db->querySingle('PRAGMA page_size');
	if ($taille_page > 0) $db->exec('PRAGMA max_page_count = '.(int)(TAILLE_MAX_BASE / $taille_page));
	$db->setAuthorizer('autorisation_sql');
	return $db;
}

// Message d'erreur SQLite échappé, complété si la base a atteint sa taille maximale
function message_erreur($db) {
	$m = htmlspecialchars($db->lastErrorMsg()).' ('.$db->lastErrorCode().')';
	if ($db->lastErrorCode() == 13) // SQLITE_FULL
		$m .= ' : la base a atteint sa taille maximale ('.(TAILLE_MAX_BASE / 1024 / 1024).' Mo).';
	return $m;
}

// Affiche un message lisible si la requête est arrêtée par la limite de durée
function arret_requete() {
	$e = error_get_last();
	if ($e && $e['type'] == E_ERROR && strpos($e['message'], 'Maximum execution time') !== false) {
		echo "<script language='javascript'>result_type='ko';</script>"
		    ."<p>Requête interrompue : durée maximale d'exécution dépassée (".DELAI_MAX." s).</p>";
	}
}

// Exécute $requete sur la base $base et affiche le résultat.
// Retourne true en cas de succès, null sinon.
function get($requete,$base) {
	$db = ouvre_base($base);
	if (is_null($db)) {
		echo "<script language='javascript'>result_type='ko';</script><p>Base inconnue ou inaccessible.</p>";
		return null;
	}
	set_time_limit(DELAI_MAX);
	static $arret_enregistre = false;
	if (!$arret_enregistre) {
		register_shutdown_function('arret_requete');
		$arret_enregistre = true;
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
			echo "<script language='javascript'>result_type='ok';</script><p>Requête exécutée ($nb ligne(s) modifiée(s))</p>";
		} else {
			echo '<table cellspacing="5" border="2" cellpadding="2"><thead><tr>';
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
				echo '<script language="javascript">result_type=\'ko\';</script><p>'.message_erreur($db).'</p>';
			} else {
				echo "<script language='javascript'>result_type='ok';</script><p> $nbtuples ligne(s) obtenue(s)"
				     .($tronque ? ' (affichage limité aux '.MAX_LIGNES.' premières lignes)' : '').'</p>';
				$ok = true;
			}
			$result->finalize();
		}
		if (is_null($ok) && $nbcol == 0) $ok = true;
	} else {
		echo '<p>Problème dans la requête</p>';
		echo "<script language='javascript'>result_type='ko';</script><p>".message_erreur($db)."</p>";
	}
	$db->close();
	return $ok;
}

function get_tables($base) {
	$requete = "SELECT name FROM sqlite_master WHERE type IN ('table','view') AND name NOT LIKE 'sqlite_%' ORDER BY lower(name)";
	$table = array();
	$db = ouvre_base($base);
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
		echo "<p><a href='#matiere'><img src='images/up_64.png' alt='Sommaire' title='Sommaire' width='32'/></a></p>";
		echo '</div></div>';
	}
}

// Sommaire des tables d'une base
function sommaire($tables) {
	echo "<a id='matiere'></a><div class='post'><h2 class='title'>Sommaire</h2><div class='story'><ul>";
	foreach($tables as $ta) {
		$nom = htmlspecialchars($ta, ENT_QUOTES);
		echo "<li><a href='#$nom'><img src='images/down_64.png' alt='' width='16'/>&nbsp;&nbsp;$nom</a></li>";
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
			    ."<img src='images/down_64.png' height='16' width='16' alt=''/> ".html($tp['nom'])."</a></li>";
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
	echo "<p><a class='btnPopup' href='tp.php?base=".html(urlencode($base))."&amp;tp=".html(urlencode($tp['nom']))."' target='_blank'>Commencer</a></p>";
	echo "</div>";
	echo "<div class='post'><h2 class='title'>Nombre de questions : ".count($questions)."</h2></div>";
	echo "<div class='post'><h2 class='title'>Mots clés</h2><h3 class='indicateur'>";
	foreach(array_keys($themes) as $t) echo html(nom_theme($t))."<br/>";
	echo "</h3></div>";
	echo "<div class='post'><h2 class='title'>Icônes utilisés : <a href='aides.php' target='_blank'>"
	    ."<img src='images/help_64.png' width='20' alt='Aide'/></a></h2></div>";
	echo "<div class='post'><h2 class='title'>Déroulement</h2><ul>"
	    ."<li>Pour débuter le TP, appuyez sur le bouton « Commencer » : la liste des questions s'ouvre dans un nouvel onglet.</li>"
	    ."<li>Pour commencer une question, cliquez sur le crayon.</li>"
	    ."<li>Lors d'une question, plusieurs choix se proposent à vous :<ul>"
	    ."<li>retourner au menu des questions avec la flèche, en mémorisant votre réponse ;</li>"
	    ."<li>mémoriser votre réponse grâce au panier ;</li>"
	    ."<li>exécuter votre requête grâce à la roue crantée (sauf pour les questions où il faut trouver l'intention).</li>"
	    ."</ul></li></ul></div>";
}

// Thèmes disponibles pour le travail thématique d'une base, avec leur difficulté
function themes_dispo($base) {
	$themes = themes_ouverts(config_base($base));
	echo "<div class='post'><h2 class='title'>Questions à thèmes</h2><ul>";
	foreach($themes as $t => $poids) {
		echo "<li><a href='questionsThem.php?base=".html(urlencode($base))."&amp;theme=".html(urlencode($t))."' target='_blank'>"
		    ."<img src='images/down_64.png' height='16' width='16' alt=''/> ".html(nom_theme($t))."</a></li>";
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
	echo "<p><a>Question $numero</a> ";
	echo "<img src='images/pencil_64.png' alt='Répondre' title='Répondre à la question' width='32' "
	    ."onClick='ouvre_question(".args_js($question)."); return false;' style='cursor:pointer'/></p>";
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
		echo "<script type='text/javascript'>
			var temps_restant = 60 * 60;
			function compte_a_rebours() {
				var s = temps_restant % 60;
				$('countdown').update(Math.floor(temps_restant / 60) + ' min ' + (s < 10 ? '0' : '') + s + ' s');
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
	?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8" />
  <title>SQL Sandbox</title>
  <link href="default.css" rel="stylesheet" type="text/css" />
  <script src="./js/prototype.js" type="text/javascript"></script>
  <script src="./js/scriptaculous/scriptaculous.js" type="text/javascript"></script>
  <script type="text/javascript">
    csrf_token = <?php echo json_encode(isset($_SESSION['csrf']) ? $_SESSION['csrf'] : ''); ?>;
  </script>
  <script src="./js/bd_base.js" type="text/javascript"></script>
</head>
<body>
<div id="content">
  <div id="posts">
<?php
}

function fin_page() {
	echo "  </div>\n</div>\n</body>\n</html>\n";
}

// Vérifie que la session a été initialisée par index.php (copies des bases)
function session_initialisee() {
	if (isset($_SESSION['debug'])) return true;
	echo "<div class='post'><h2 class='title'>Session non initialisée</h2><div class='story'>"
	    ."<p>Ouvrez d'abord l'application depuis sa page d'accueil, puis relancez cette page.</p></div></div>";
	return false;
}
?>