<?php
//============================================================================
// Exercices au format de QueryCraft.
//
// Un exercice est un fichier JSON {"database": {"type": "sqlite", "name": "em.db"},
// "questions": {code: [requete, intention, commentaire, type, instructions(, modele)]}}.
// Il porte sur la base dont le fichier s'appelle database.name (em.db -> bdd/em/em.db).
//
// Les exercices viennent :
// - par défaut, du dossier local bdd/exercices/ (protégé comme les bases) ;
// - si exos_source est renseigné dans le fichier sqlsandbox.ini (hors du site), du
//   catalogue d'une instance QueryCraft (démo ou tenant), synchronisé à la demande :
//   <source>/catalogue/exos, avec le jeton exos_jeton. Le catalogue est gardé en cache
//   dans le dossier de travail ; si QueryCraft ne répond pas, le dernier cache sert,
//   et à défaut les exercices locaux.
//
// Avec une source, SQLSandbox reproduit aussi les BASES de l'instance QueryCraft (catalogue
// au format 2) : une base nouvelle ou modifiée est téléchargée (empreinte sha256 vérifiée)
// avec sa description, dans un dossier de travail hors du site (DOSSIER_BASES), jamais dans
// bdd/ ; une base retirée de QueryCraft passe à la corbeille (vidée après 7 jours). Dès
// qu'une base a été synchronisée, ce sont ces bases qui sont proposées (bdd/ ne sert plus
// qu'en l'absence de source ou de synchronisation réussie).
//
// Types de question (voir querycraft/exercicemanager.py) : à gauche de la flèche ce que
// reçoit l'étudiant, à droite ce qu'il produit. I : intention, R : requête, r : requête à
// trous (6e élément), T : table résultat affichée d'emblée. …->R : écrire la requête ;
// …->I : rédiger l'intention de la requête donnée (correction par IA).
//============================================================================
require_once __DIR__.'/dossiers.php';

define('EXOS_DOSSIER_LOCAL', __DIR__.'/bdd/exercices');
define('EXOS_TYPES', array('I->R', 'IT->R', 'rI->R', 'rIT->R', 'R->I', 'RT->I'));
define('EXOS_TTL', 300);          // secondes entre deux vérifications du catalogue distant
define('EXOS_DELAI_APPEL', 10);   // secondes d'attente de la réponse de QueryCraft
define('EXOS_TAILLE_MAX', 5 * 1024 * 1024);
define('BASES_TAILLE_MAX', 20 * 1024 * 1024);   // taille maximale d'une base téléchargée
define('BASES_CORBEILLE_JOURS', 7);              // conservation des bases retirées

// Bases synchronisées depuis QueryCraft : <DOSSIER_BASES>/<nom>/<nom>.db, description.html
// et meta.json (hors du site, comme les copies de session ; null si aucun dossier utilisable)
if (!defined('DOSSIER_BASES')) define('DOSSIER_BASES', dossier_prive(INSTANCE.'-bases', __DIR__.'/tmp/bases'));

// Réglages de la source des exercices, lus dans le fichier ini $fichier.
// exos_source vide (ou fichier absent) : exercices locaux.
function exos_lit_ini($fichier) {
	$ini = (is_file($fichier) && is_readable($fichier)) ? @parse_ini_file($fichier) : false;
	if (!is_array($ini)) $ini = array();
	$source = rtrim(trim((string)($ini['exos_source'] ?? '')), '/');
	if ($source !== '') {
		// Le jeton ne circule en clair que vers la machine elle-même : HTTPS exigé sauf localhost
		$hote = parse_url($source, PHP_URL_HOST);
		$local = in_array($hote, array('localhost', '127.0.0.1', '[::1]'), true);
		$schema = parse_url($source, PHP_URL_SCHEME);
		if (!($schema === 'https' || ($schema === 'http' && $local))) {
			error_log("SQLSandbox exercices : exos_source doit être en HTTPS ($source ignorée)");
			$source = '';
		}
	}
	return array(
		'source' => $source,
		'jeton' => trim((string)($ini['exos_jeton'] ?? '')),
		'ttl' => max(0, (int)($ini['exos_ttl'] ?? EXOS_TTL)),
	);
}

// Réglages de la source des exercices. Le fichier est cherché d'abord dans la variable
// d'environnement SQLSANDBOX_INI, puis à côté de la racine web (~/sqlsandbox.ini chez OVH),
// comme le fichier de l'IA.
function exos_config() {
	static $conf = null;
	if (!is_null($conf)) return $conf;
	$candidats = array();
	if (getenv('SQLSANDBOX_INI')) $candidats[] = getenv('SQLSANDBOX_INI');
	if (!empty($_SERVER['DOCUMENT_ROOT']))
		$candidats[] = dirname(rtrim($_SERVER['DOCUMENT_ROOT'], '/')).'/'.INSTANCE.'.ini';
	foreach ($candidats as $f) if (is_file($f)) return $conf = exos_lit_ini($f);
	return $conf = exos_lit_ini('');
}

// Exercices valides d'un tableau décodé {code: {database, questions}} : les codes et les
// questions mal formés sont écartés (le catalogue distant n'est pas de confiance).
function exos_valides($exercices) {
	$ok = array();
	if (!is_array($exercices)) return $ok;
	foreach ($exercices as $code => $exo) {
		if (!is_string($code) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $code)) continue;
		if (!is_array($exo) || !isset($exo['questions']) || !is_array($exo['questions'])) continue;
		$questions = array();
		foreach ($exo['questions'] as $cq => $q) {
			if (!is_string($cq) && !is_int($cq)) continue;
			if (!is_array($q) || !in_array(count($q), array(5, 6), true)) continue;
			$q = array_values($q);
			foreach ($q as $champ) if (!is_string($champ)) continue 2;
			$questions[(string)$cq] = $q;
		}
		$db = (isset($exo['database']) && is_array($exo['database'])) ? $exo['database'] : null;
		$ok[$code] = array('database' => $db, 'questions' => $questions);
	}
	return $ok;
}

// Exercices du dossier local
function exos_locaux() {
	$exercices = array();
	foreach ((array)glob(EXOS_DOSSIER_LOCAL.'/*.json') as $f) {
		$d = json_decode((string)@file_get_contents($f), true);
		if (is_array($d)) $exercices[basename($f, '.json')] = $d;
	}
	return exos_valides($exercices);
}

// Appel du catalogue distant. Retourne array(code HTTP, corps, ETag reçu).
function exos_appel($conf, $etag) {
	$entetes = array('Accept: application/json', 'Authorization: Bearer '.$conf['jeton']);
	if ($etag !== '') $entetes[] = 'If-None-Match: '.$etag;
	$etag_recu = '';
	$c = curl_init($conf['source'].'/catalogue/exos');
	curl_setopt_array($c, array(
		CURLOPT_HTTPHEADER => $entetes,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_TIMEOUT => EXOS_DELAI_APPEL,
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
		CURLOPT_USERAGENT => 'SQLSandbox',
		CURLOPT_HEADERFUNCTION => function ($c, $ligne) use (&$etag_recu) {
			if (stripos($ligne, 'ETag:') === 0) $etag_recu = trim(substr($ligne, 5));
			return strlen($ligne);
		},
	));
	$corps = curl_exec($c);
	$code = (int)curl_getinfo($c, CURLINFO_RESPONSE_CODE);
	if ($code == 0) error_log('SQLSandbox exercices : catalogue injoignable : '.curl_error($c));
	curl_close($c);
	return array($code, is_string($corps) ? $corps : '', $etag_recu);
}

// Exercices du catalogue distant, depuis le cache du dossier de travail, rafraîchi au plus
// une fois toutes les ttl secondes. Retourne null si aucun catalogue n'a jamais été obtenu.
function exos_distants($conf) {
	if (is_null(DOSSIER_TMP) || !function_exists('curl_init')) return null;
	$fic = DOSSIER_TMP.'/exos-catalogue.json';
	$cache = is_file($fic) ? json_decode((string)@file_get_contents($fic), true) : null;
	if (!is_array($cache) || ($cache['source'] ?? '') !== $conf['source']) $cache = null;
	$a_jour = $cache && (time() - (int)($cache['verifie'] ?? 0)) < $conf['ttl'];
	if (!$a_jour) {
		// Un seul processus rafraîchit le cache ; les autres servent l'existant
		$verrou = @fopen(DOSSIER_TMP.'/exos-catalogue.verrou', 'c');
		if ($verrou && flock($verrou, LOCK_EX | LOCK_NB)) {
			list($code, $corps, $etag) = exos_appel($conf, $cache ? (string)($cache['etag'] ?? '') : '');
			if ($code == 200 && strlen($corps) <= EXOS_TAILLE_MAX) {
				$d = json_decode($corps, true);
				if (is_array($d) && isset($d['exercices'])) {
					$cache = array('source' => $conf['source'], 'etag' => $etag, 'verifie' => time(),
					               'instance' => (string)($d['instance'] ?? ''),
					               'exercices' => exos_valides($d['exercices']));
					// Format 2 : les bases suivent ; si leur synchronisation n'a pas abouti, l'empreinte
					// n'est pas gardée, pour retenter au prochain passage
					if (isset($d['bases']) && is_array($d['bases']) && !bases_synchronise($conf, $d['bases']))
						$cache['etag'] = '';
				} else {
					error_log('SQLSandbox exercices : réponse illisible du catalogue');
				}
			} elseif ($code == 304 && $cache) {
				$cache['verifie'] = time();
			} elseif ($code != 0) {
				error_log("SQLSandbox exercices : le catalogue répond $code (jeton ? source ?)");
			}
			if ($cache) {
				$tmp = $fic.'.'.getmypid();
				if (@file_put_contents($tmp, json_encode($cache, JSON_UNESCAPED_UNICODE)) !== false) @rename($tmp, $fic);
			}
			flock($verrou, LOCK_UN);
		}
		if ($verrou) fclose($verrou);
	}
	return $cache ? $cache['exercices'] : null;
}

// Tous les exercices disponibles : distants si une source est configurée et joignable
// (ou en cache), sinon locaux.
function exos_catalogue() {
	static $exercices = null;
	if (!is_null($exercices)) return $exercices;
	$conf = exos_config();
	if ($conf['source'] !== '') {
		$d = exos_distants($conf);
		if (!is_null($d)) return $exercices = $d;
	}
	return $exercices = exos_locaux();
}

// Nom du fichier de la base $base de la session (em.db), ou null
function exos_fichier_base($base) {
	if (!is_string($base) || !isset($_SESSION['originaux'][$base])) return null;
	return basename($_SESSION['originaux'][$base]);
}

// Exercices de la base $base : ceux dont database désigne son fichier SQLite. Un exercice
// sans base associée n'est pas proposé : SQLSandbox ne saurait sur quelle base l'exécuter.
function exos_de_base($base) {
	$fichier = exos_fichier_base($base);
	$liste = array();
	if (is_null($fichier)) return $liste;
	foreach (exos_catalogue() as $code => $exo) {
		$db = $exo['database'];
		if ($db && ($db['type'] ?? '') === 'sqlite' && ($db['name'] ?? '') === $fichier) $liste[$code] = $exo;
	}
	ksort($liste, SORT_NATURAL | SORT_FLAG_CASE);
	return $liste;
}

// Lecture d'un type de question : array('modele' => bool, 'table' => bool, 'attendu' => 'R'|'I').
// Un type inconnu ou vide est lu comme I->R.
function exos_type($type) {
	if (!in_array($type, EXOS_TYPES, true)) return array('modele' => false, 'table' => false, 'attendu' => 'R');
	list($gauche, $droite) = explode('->', $type);
	return array('modele' => $gauche[0] === 'r', 'table' => strpos($gauche, 'T') !== false, 'attendu' => $droite);
}

// Question $code_q de l'exercice $code_ex, rattachée à la base $base, ou null :
// array('requete', 'intention', 'commentaire', 'type', 'instructions', 'modele').
function exos_question($base, $code_ex, $code_q) {
	if (!is_string($code_ex) || !is_string($code_q)) return null;
	$exos = exos_de_base($base);
	if (!isset($exos[$code_ex]['questions'][$code_q])) return null;
	$q = $exos[$code_ex]['questions'][$code_q];
	return array('requete' => $q[0], 'intention' => $q[1], 'commentaire' => $q[2], 'type' => $q[3],
	             'instructions' => $q[4], 'modele' => $q[5] ?? '');
}

// Table résultat (HTML) de $requete sur la base d'ORIGINE de $base (lecture seule) : la table
// attendue ne dépend pas des modifications que l'étudiant a faites dans sa copie.
function exos_table_resultat($base, $requete) {
	if (!isset($_SESSION['originaux'][$base])) return '';
	try {
		$db = new SQLite3($_SESSION['originaux'][$base], SQLITE3_OPEN_READONLY);
	} catch (Exception $e) {
		return '';
	}
	$db->enableExceptions(false);
	$db->busyTimeout(DELAI_MAX * 1000);
	$r = @$db->query($requete);
	if (!$r || $r->numColumns() == 0) {
		$db->close();
		return '<p>Table indisponible.</p>';
	}
	$t = '<table class="resultat"><thead><tr>';
	for ($i = 0; $i < $r->numColumns(); $i++) $t .= '<th>'.htmlspecialchars($r->columnName($i)).'</th>';
	$t .= '</tr></thead><tbody>';
	$n = 0;
	while (($l = @$r->fetchArray(SQLITE3_NUM)) && $n < MAX_LIGNES) {
		$t .= '<tr>';
		foreach ($l as $v) $t .= '<td>'.htmlspecialchars((string)$v).'</td>';
		$t .= '</tr>';
		$n++;
	}
	$t .= "</tbody></table><p>$n ligne(s)</p>";
	$r->finalize();
	$db->close();
	return $t;
}

// Données d'une question envoyées au navigateur : jamais la solution d'une question …->R,
// jamais l'intention (la réponse) d'une question …->I.
function exos_question_client($base, $code_ex, $code_q, $q, $numero) {
	$type = exos_type($q['type']);
	$intention = $type['attendu'] === 'I';
	return array(
		'base' => (string)$base,
		'exercice' => (string)$code_ex,
		'code' => (string)$code_q,
		'numero' => $numero,
		'attendu' => $type['attendu'],
		'intention' => $intention ? '' : $q['intention'],
		'instructions' => $q['instructions'],
		'requete' => $intention ? $q['requete'] : '',
		'modele' => $type['modele'] ? $q['modele'] : '',
		'table' => $type['table'] ? exos_table_resultat($base, $q['requete']) : '',
		'aide' => $q['commentaire'],
	);
}

//============================================================================
// Bases synchronisées depuis QueryCraft
//============================================================================

// Nom de fichier de base acceptable (em.db), sans chemin
function bases_nom_valide($fichier) {
	return is_string($fichier) && preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,99}\.db$/', $fichier) === 1;
}

// Télécharge la base $fichier du catalogue dans $cible, en vérifiant taille, empreinte et
// format. Retourne true si la base est en place.
function bases_telecharge($conf, $fichier, $sha256, $cible) {
	$tmp = $cible.'.'.getmypid().'.tmp';
	$f = @fopen($tmp, 'wb');
	if (!$f) return false;
	$c = curl_init($conf['source'].'/catalogue/bases/'.rawurlencode($fichier));
	curl_setopt_array($c, array(
		CURLOPT_HTTPHEADER => array('Authorization: Bearer '.$conf['jeton']),
		CURLOPT_FILE => $f,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
		CURLOPT_USERAGENT => 'SQLSandbox',
		CURLOPT_NOPROGRESS => false,
		// Coupe le transfert au-delà de la taille maximale
		CURLOPT_PROGRESSFUNCTION => function ($c, $total, $recu) { return $recu > BASES_TAILLE_MAX ? 1 : 0; },
	));
	$ok = curl_exec($c) && (int)curl_getinfo($c, CURLINFO_RESPONSE_CODE) == 200;
	fclose($f);
	if ($ok && filesize($tmp) > 0 && filesize($tmp) <= BASES_TAILLE_MAX
	    && hash_equals($sha256, hash_file('sha256', $tmp))
	    && file_get_contents($tmp, false, null, 0, 16) === "SQLite format 3\0") {
		try {
			$db = new SQLite3($tmp, SQLITE3_OPEN_READONLY);
			$ok = $db->querySingle('PRAGMA quick_check') === 'ok';
			$db->close();
		} catch (Exception $e) {
			$ok = false;
		}
		if ($ok && @rename($tmp, $cible)) return true;
	}
	error_log("SQLSandbox bases : téléchargement de $fichier refusé ou incomplet");
	@unlink($tmp);
	return false;
}

// Reproduit dans DOSSIER_BASES les bases $bases du catalogue (fichier => {sha256, taille,
// description}). Retourne false si une base n'a pas pu être mise à jour.
function bases_synchronise($conf, $bases) {
	if (is_null(DOSSIER_BASES)) return false;
	$tout_ok = true;
	$presentes = array();
	foreach ($bases as $fichier => $b) {
		if (!bases_nom_valide($fichier) || !is_array($b) || !is_string($b['sha256'] ?? null)
		    || !preg_match('/^[0-9a-f]{64}$/', $b['sha256']) || (int)($b['taille'] ?? 0) > BASES_TAILLE_MAX) continue;
		$nom = substr($fichier, 0, -3);
		$presentes[$nom] = true;
		$dossier = DOSSIER_BASES.'/'.$nom;
		if (!is_dir($dossier) && !@mkdir($dossier, 0700)) { $tout_ok = false; continue; }
		$meta_fic = $dossier.'/meta.json';
		$meta = is_file($meta_fic) ? json_decode((string)@file_get_contents($meta_fic), true) : null;
		if (!is_array($meta)) $meta = array();
		if (($meta['sha256'] ?? '') !== $b['sha256'] || !is_file($dossier.'/'.$fichier)) {
			if (!bases_telecharge($conf, $fichier, $b['sha256'], $dossier.'/'.$fichier)) { $tout_ok = false; continue; }
		}
		// Description : le HTML est rendu (et assaini) par QueryCraft
		$d = (isset($b['description']) && is_array($b['description'])) ? $b['description'] : array();
		$meta = array(
			'fichier' => $fichier,
			'sha256' => $b['sha256'],
			'nom' => preg_replace('/[^\p{L}\p{N} _.-]/u', '', (string)($d['nom'] ?? '')) ?: $nom,
			'reference' => preg_match('#^https?://#i', (string)($d['reference'] ?? '')) ? (string)$d['reference'] : '',
			'origine' => (string)($d['origine'] ?? ''),
			'synchronise' => date('c'),
		);
		@file_put_contents($dossier.'/description.html', (string)($d['html'] ?? ''));
		@file_put_contents($meta_fic.'.tmp', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
		@rename($meta_fic.'.tmp', $meta_fic);
	}
	// Bases retirées de QueryCraft : à la corbeille, seulement sur un catalogue non vide
	if (count($presentes) > 0) {
		$corbeille = DOSSIER_BASES.'/.corbeille';
		foreach (bases_dossiers() as $nom => $dossier) {
			if (isset($presentes[$nom])) continue;
			if (!is_dir($corbeille)) @mkdir($corbeille, 0700);
			@rename($dossier, $corbeille.'/'.$nom.'-'.date('Ymd-His'));
			error_log("SQLSandbox bases : $nom retirée de QueryCraft, mise à la corbeille");
		}
		foreach ((array)glob($corbeille.'/*', GLOB_ONLYDIR) as $vieux) {
			if (filemtime($vieux) < time() - BASES_CORBEILLE_JOURS * 86400) bases_supprime_dossier($vieux);
		}
	}
	return $tout_ok;
}

function bases_supprime_dossier($dossier) {
	foreach ((array)glob($dossier.'/*') as $f) @unlink($f);
	@rmdir($dossier);
}

// Dossiers des bases synchronisées complètes : nom => dossier
function bases_dossiers() {
	$liste = array();
	if (is_null(DOSSIER_BASES)) return $liste;
	foreach ((array)glob(DOSSIER_BASES.'/*', GLOB_ONLYDIR) as $dossier) {
		$nom = basename($dossier);
		if (is_file("$dossier/meta.json") && is_file("$dossier/$nom.db")) $liste[$nom] = $dossier;
	}
	return $liste;
}

// Bases synchronisées, au format de $listeBase (index.php), ou null si SQLSandbox n'a pas de
// source d'exercices ou n'a encore synchronisé aucune base (on garde alors bdd/).
function bases_synchronisees() {
	if (exos_config()['source'] === '') return null;
	exos_catalogue(); // rafraîchit le catalogue (et les bases) si son délai est écoulé
	$liste = array();
	foreach (bases_dossiers() as $nom => $dossier) {
		$meta = json_decode((string)@file_get_contents("$dossier/meta.json"), true);
		if (!is_array($meta)) continue;
		$affiche = (string)($meta['nom'] ?? '') !== '' ? $meta['nom'] : $nom;
		$liste[$affiche] = array(
			'config' => null,
			'description_html' => "$dossier/description.html",
			'fichier' => "$dossier/$nom.db",
			'prefixe' => '',
			'référence' => (string)($meta['reference'] ?? ''),
			'description' => (string)@file_get_contents("$dossier/description.html"),
			'tables' => array(),
		);
	}
	return count($liste) > 0 ? $liste : null;
}

