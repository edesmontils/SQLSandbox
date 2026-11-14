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
// Tenants : n'importe qui peut essayer un code à 6 chiffres, et chaque code inconnu coûte un
// appel à QueryCraft. Un refus est donc retenu un moment, et les vérifications de tenants
// encore inconnus (sans cache) sont limitées pour toute l'application.
define('TENANTS_REFUS_DUREE', 120);      // secondes pendant lesquelles un refus est retenu
define('TENANTS_ESSAIS_MINUTE', 20);     // vérifications de tenants sans cache, par minute

// Bases synchronisées depuis QueryCraft : <DOSSIER_BASES>/<nom>/<nom>.db, description.html (et .md)
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
		// Plafonds d'espace disque des copies (fonctions.php, espace_sature), en Mo dans le fichier
		'occupation_max' => isset($ini['occupation_max_mo']) ? max(1, (int)$ini['occupation_max_mo']) * 1048576 : OCCUPATION_MAX,
		'espace_libre_min' => isset($ini['espace_libre_min_mo']) ? max(0, (int)$ini['espace_libre_min_mo']) * 1048576 : ESPACE_LIBRE_MIN,
	);
}

// Réglages de la source des exercices. Le fichier est cherché d'abord dans la variable
// d'environnement SQLSANDBOX_INI, puis à côté de la racine web (~/sqlsandbox.ini chez OVH),
// comme le fichier de l'IA.
function exos_ini() {
	static $conf = null;
	if (!is_null($conf)) return $conf;
	$candidats = array();
	if (getenv('SQLSANDBOX_INI')) $candidats[] = getenv('SQLSANDBOX_INI');
	if (!empty($_SERVER['DOCUMENT_ROOT']))
		$candidats[] = dirname(rtrim($_SERVER['DOCUMENT_ROOT'], '/')).'/'.INSTANCE.'.ini';
	foreach ($candidats as $f) if (is_file($f)) return $conf = exos_lit_ini($f);
	return $conf = exos_lit_ini('');
}

// Tenants de QueryCraft. /SQLSandbox/123456 (réécrite en index.php?qc=123456) suit le tenant
// 123456 de l'instance exos_source : ses exercices et ses bases, et le bouton « Ouvrir dans
// QueryCraft » y mène. Le tenant est gardé dans la session (les appels AJAX et les pages de TP
// n'ont pas le code dans leur adresse) ; '' : l'instance exos_source elle-même (la démo).
// QueryCraft n'ouvre le catalogue d'un tenant qu'au jeton exos_jeton s'il s'agit de son jeton
// général et que l'enseignant a autorisé SQLSandbox (« Partage avec SQLSandbox »).
function tenant_valide($code) {
	return is_string($code) && preg_match('/^[0-9]{6}$/D', $code) === 1;
}

// Réglages de la source pour le tenant $tenant ('' : la source elle-même)
function exos_conf_tenant($ini, $tenant) {
	$ini['tenant'] = ($ini['source'] !== '' && tenant_valide($tenant)) ? $tenant : '';
	if ($ini['tenant'] !== '') $ini['source'] .= '/'.$ini['tenant'];
	return $ini;
}

// Réglages de la source des exercices pour le tenant de la session
function exos_config() {
	static $conf = null;
	if (!is_null($conf)) return $conf;
	return $conf = exos_conf_tenant(exos_ini(), (string)($_SESSION['qc'] ?? ''));
}

// Choix du tenant à l'ouverture de l'application (index.php) : $demande est le code de
// l'adresse (null pour /SQLSandbox/, donc l'instance de la source). Un tenant injoignable,
// inconnu ou qui n'autorise pas SQLSandbox ramène à la source, et le message est renvoyé.
// Changer de tenant oublie les copies de bases de la session : deux tenants peuvent avoir
// une base du même nom, mais pas le même contenu.
function tenant_choisi($demande) {
	$ini = exos_ini();
	$tenant = ($ini['source'] !== '' && tenant_valide($demande)) ? $demande : '';
	$message = null;
	if ($tenant !== '') {
		$conf_t = exos_conf_tenant($ini, $tenant);
		$inconnu = "L'instance QueryCraft $tenant est inconnue, injoignable ou n'autorise pas SQLSandbox"
		         ." (réglage « Partage avec SQLSandbox » de sa configuration) : voici les bases par défaut.";
		if (tenants_refuse($tenant)) {
			$message = $inconnu;
		} elseif (!is_file(exos_fichier_cache($conf_t)) && !tenants_essai_permis()) {
			$message = "Trop de vérifications d'instances QueryCraft en ce moment : réessayez dans une minute."
			         ." Voici les bases par défaut.";
		} elseif (is_null(exos_distants($conf_t))) {
			$message = $inconnu;
		}
		if (!is_null($message)) $tenant = '';
	}
	if ((string)($_SESSION['qc'] ?? '') !== $tenant) {
		if (function_exists('purge_db')) purge_db();
		foreach (array_keys($_SESSION) as $cle)
			if (substr($cle, 0, 6) === 'copie_') unset($_SESSION[$cle]);
		unset($_SESSION['configs'], $_SESSION['descriptions'], $_SESSION['references'], $_SESSION['originaux']);
		$_SESSION['qc'] = $tenant;
	}
	return $message;
}

// Registre des tenants (exos-tenants.json du dossier de travail) : refus récents
// {code: date} et compteur des vérifications de la minute en cours. $modifie reçoit le
// registre et le modifie ; la valeur qu'il renvoie est renvoyée. Sans dossier de travail,
// $modifie reçoit un registre vide, non gardé.
function tenants_registre($modifie) {
	$vide = array('refus' => array(), 'minute' => 0, 'essais' => 0);
	if (is_null(DOSSIER_TMP)) { $r = $vide; return $modifie($r); }
	$f = @fopen(DOSSIER_TMP.'/exos-tenants.json', 'c+');
	if (!$f) { $r = $vide; return $modifie($r); }
	flock($f, LOCK_EX);
	$r = json_decode((string)stream_get_contents($f), true);
	if (!is_array($r) || !is_array($r['refus'] ?? null)) $r = $vide;
	// Refus expirés oubliés : le registre reste petit
	foreach ($r['refus'] as $code => $date)
		if (!tenant_valide((string)$code) || time() - (int)$date >= TENANTS_REFUS_DUREE) unset($r['refus'][$code]);
	$avant = json_encode($r);
	$resultat = $modifie($r);
	if (json_encode($r) !== $avant) {
		ftruncate($f, 0);
		rewind($f);
		fwrite($f, json_encode($r));
		fflush($f);
	}
	flock($f, LOCK_UN);
	fclose($f);
	return $resultat;
}

// Vrai si le tenant $tenant a été refusé il y a moins de TENANTS_REFUS_DUREE secondes
function tenants_refuse($tenant) {
	return tenants_registre(function (&$r) use ($tenant) { return isset($r['refus'][$tenant]); });
}

// Retient le refus du tenant $tenant (inconnu, non autorisé ou injoignable)
function tenants_note_refus($tenant) {
	if (!tenant_valide($tenant)) return;
	tenants_registre(function (&$r) use ($tenant) { $r['refus'][$tenant] = time(); return null; });
}

// Compte une vérification d'un tenant sans cache. Retourne false si la limite de la
// minute (TENANTS_ESSAIS_MINUTE) est atteinte.
function tenants_essai_permis() {
	return tenants_registre(function (&$r) {
		$minute = intdiv(time(), 60);
		if ((int)$r['minute'] !== $minute) { $r['minute'] = $minute; $r['essais'] = 0; }
		if ($r['essais'] >= TENANTS_ESSAIS_MINUTE) return false;
		$r['essais']++;
		return true;
	});
}

// Fichier du cache du catalogue (exos-catalogue.json, exos-catalogue-123456.json pour un tenant)
function exos_fichier_cache($conf, $extension = 'json') {
	$tenant = (string)($conf['tenant'] ?? '');
	return DOSSIER_TMP.'/exos-catalogue'.(tenant_valide($tenant) ? "-$tenant" : '').'.'.$extension;
}

// Dossier des bases synchronisées : DOSSIER_BASES, ou DOSSIER_BASES/.tenant-123456 pour un
// tenant (dossier caché : jamais pris pour une base de la source), ou null
function bases_racine($conf = null) {
	if (is_null(DOSSIER_BASES)) return null;
	$tenant = (string)((is_null($conf) ? exos_config() : $conf)['tenant'] ?? '');
	if (!tenant_valide($tenant)) return DOSSIER_BASES;
	$dossier = DOSSIER_BASES.'/.tenant-'.$tenant;
	if (!is_dir($dossier) && !@mkdir($dossier, 0700)) return null;
	return $dossier;
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
	// pas de curl_close() : sans effet depuis PHP 8.0 et obsolète en 8.5, la poignée est libérée seule
	return array($code, is_string($corps) ? $corps : '', $etag_recu);
}

// Exercices du catalogue distant, depuis le cache du dossier de travail, rafraîchi au plus
// une fois toutes les ttl secondes. Retourne null si aucun catalogue n'a jamais été obtenu.
function exos_distants($conf) {
	if (is_null(DOSSIER_TMP) || !function_exists('curl_init')) return null;
	$fic = exos_fichier_cache($conf);
	$cache = is_file($fic) ? json_decode((string)@file_get_contents($fic), true) : null;
	if (!is_array($cache) || ($cache['source'] ?? '') !== $conf['source']) $cache = null;
	$a_jour = $cache && (time() - (int)($cache['verifie'] ?? 0)) < $conf['ttl'];
	if (!$a_jour) {
		// Un seul processus rafraîchit le cache ; les autres servent l'existant
		$verrou = @fopen(exos_fichier_cache($conf, 'verrou'), 'c');
		if ($verrou && flock($verrou, LOCK_EX | LOCK_NB)) {
			// Cache antérieur à la version 7.2 (sans base_active), ou base synchronisée avant que la
			// description Markdown soit gardée (description.md) : relu en entier, car QueryCraft
			// répondrait 304 tant que son catalogue ne change pas
			$etag_cache = ($cache && array_key_exists('base_active', $cache) && !bases_sans_markdown($conf))
			            ? (string)($cache['etag'] ?? '') : '';
			list($code, $corps, $etag) = exos_appel($conf, $etag_cache);
			if ($code == 200 && strlen($corps) <= EXOS_TAILLE_MAX) {
				$d = json_decode($corps, true);
				if (is_array($d) && isset($d['exercices'])) {
					$cache = array('source' => $conf['source'], 'etag' => $etag, 'verifie' => time(),
					               'instance' => (string)($d['instance'] ?? ''),
					               'base_active' => bases_nom_valide($d['base_active'] ?? null) ? $d['base_active'] : '',
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
			} elseif (in_array($code, array(401, 403, 404), true)) {
				// Refus explicite (jeton révoqué, partage retiré, tenant supprimé) : le cache ne
				// doit plus servir, sinon l'accès survivrait à sa révocation. Seule une panne
				// (réseau, 5xx) laisse servir le dernier catalogue obtenu.
				error_log("SQLSandbox exercices : le catalogue refuse l'accès ($code) : cache oublié");
				$cache = null;
				@unlink($fic);
			} elseif ($code != 0) {
				error_log("SQLSandbox exercices : le catalogue répond $code (jeton ? source ?)");
			}
			if ($cache) {
				$tmp = $fic.'.'.getmypid();
				if (@file_put_contents($tmp, json_encode($cache, JSON_UNESCAPED_UNICODE)) !== false) @rename($tmp, $fic);
			}
			flock($verrou, LOCK_UN);
			// Rien à garder (tenant inconnu, par exemple) : pas de verrou laissé, sans quoi
			// chaque code essayé laisserait un fichier dans le dossier de travail
			if (!$cache) @unlink(exos_fichier_cache($conf, 'verrou'));
			// Tenant refusé ou injoignable : retenu, pour qu'un nouvel essai ne rappelle pas
			// QueryCraft tout de suite (voir tenant_choisi)
			if (!$cache) tenants_note_refus((string)($conf['tenant'] ?? ''));
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

// Instance QueryCraft vers laquelle envoyer la requête d'un étudiant : son adresse et le
// fichier de la base qu'elle interroge (em.db), lus dans le catalogue ; null sans source,
// sans catalogue reçu, ou si l'instance n'interroge pas une de ses bases SQLite.
// $conf : réglages de la source (ceux de sqlsandbox.ini par défaut).
function querycraft_cible($conf = null) {
	if (is_null($conf)) {
		$conf = exos_config();
		if ($conf['source'] !== '') exos_catalogue(); // rafraîchit le cache si son délai est écoulé
	}
	if ($conf['source'] === '' || is_null(DOSSIER_TMP)) return null;
	$cache = json_decode((string)@file_get_contents(exos_fichier_cache($conf)), true);
	if (!is_array($cache) || ($cache['source'] ?? '') !== $conf['source']) return null;
	$base = (string)($cache['base_active'] ?? '');
	if (!bases_nom_valide($base)) return null;
	return array('url' => $conf['source'], 'fichier' => $base);
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
// La requête vient du catalogue, écrit par l'enseignant d'un tenant : elle n'est pas de
// confiance et passe par les mêmes protections que celles des étudiants (autorizer, mémoire,
// durée, places d'exécution par adresse IP). Le résultat est gardé en cache (table_* du
// dossier de travail, purgés par index.php) : la page d'un TP ne réexécute pas ses requêtes.
// Une requête arrêtée par la limite de durée laisse « Table indisponible » dans le cache,
// pour ne pas être relancée à chaque affichage.
function exos_table_resultat($base, $requete) {
	if (!isset($_SESSION['originaux'][$base])) return '';
	$original = $_SESSION['originaux'][$base];
	$indisponible = '<p>Table indisponible.</p>';
	if (!sqlite_recent() && utilise_fenetrage($requete)) return $indisponible;
	$cache = is_null(DOSSIER_TMP) ? null
	       : DOSSIER_TMP.'/table_'.hash('sha256', $original."\n".@filemtime($original)."\n".$requete).'.html';
	if (!is_null($cache) && is_file($cache)) {
		@touch($cache); // gardé tant qu'il sert
		return (string)@file_get_contents($cache);
	}
	if (!reserve_execution()) return $indisponible;
	if (!is_null($cache)) @file_put_contents($cache, $indisponible);
	try {
		$db = new SQLite3($original, SQLITE3_OPEN_READONLY);
	} catch (Exception $e) {
		return $indisponible;
	}
	limite_connexion($db);
	$db->setAuthorizer('autorisation_sql');
	set_time_limit(DELAI_MAX);
	$r = @$db->query($requete);
	if (!$r || $r->numColumns() == 0) {
		$db->close();
		return $indisponible;
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
	$erreur = !in_array($db->lastErrorCode(), array(0, 100, 101), true); // erreur en cours de lecture
	$r->finalize();
	$db->close();
	if ($erreur) return $indisponible;
	if (!is_null($cache)) {
		$tmp = $cache.'.'.getmypid();
		if (@file_put_contents($tmp, $t) !== false) @rename($tmp, $cache);
	}
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

// Description HTML venue de QueryCraft : rédigée par l'enseignant d'un tenant, elle n'est pas
// de confiance, même si QueryCraft l'assainit déjà. Seules les balises et attributs de mise
// en forme sont gardés ; les liens et images ne pointent que vers http(s) (ou mailto pour
// un lien) ou une adresse relative. Les éléments actifs (script, style, iframe, form...)
// disparaissent avec leur contenu, les balises inconnues sont retirées en gardant leur texte.
define('HTML_BALISES', array('p', 'br', 'hr', 'div', 'span', 'ul', 'ol', 'li', 'dl', 'dt', 'dd',
	'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ins', 'mark', 'small', 'sub', 'sup', 'code', 'pre',
	'kbd', 'samp', 'var', 'blockquote', 'q', 'cite', 'abbr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
	'table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'col', 'colgroup',
	'a', 'img', 'figure', 'figcaption'));
define('HTML_ATTRIBUTS', array('class', 'title', 'lang', 'colspan', 'rowspan', 'scope', 'start',
	'href', 'src', 'alt', 'width', 'height'));
define('HTML_SUPPRIMES', array('script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed',
	'applet', 'template', 'noscript', 'svg', 'math', 'form', 'input', 'button', 'textarea', 'select',
	'option', 'title', 'head', 'meta', 'link', 'base', 'audio', 'video', 'source', 'track', 'canvas'));

// Adresse acceptable pour l'attribut $attribut (href ou src)
function html_url_sure($url, $attribut) {
	// Les navigateurs ignorent blancs et caractères de contrôle dans le schéma (« java\tscript: »)
	$u = preg_replace('/[\x00-\x20\x7f]+/', '', (string)$url);
	if (!preg_match('/^([a-z][a-z0-9+.-]*):/i', $u, $m)) return true; // adresse relative ou ancre
	$schemas = $attribut === 'href' ? array('http', 'https', 'mailto') : array('http', 'https');
	return in_array(strtolower($m[1]), $schemas, true);
}

function html_assainit_noeud($parent) {
	for ($n = $parent->firstChild; $n; $n = $suivant) {
		$suivant = $n->nextSibling;
		if ($n instanceof DOMText) continue; // texte (et CDATA), échappé à la sérialisation
		if (!($n instanceof DOMElement)) { $parent->removeChild($n); continue; } // commentaires...
		$nom = strtolower($n->nodeName);
		if (in_array($nom, HTML_SUPPRIMES, true)) { $parent->removeChild($n); continue; }
		html_assainit_noeud($n);
		if (!in_array($nom, HTML_BALISES, true)) { // balise inconnue : son contenu prend sa place
			while ($n->firstChild) $parent->insertBefore($n->firstChild, $n);
			$parent->removeChild($n);
			continue;
		}
		foreach (iterator_to_array($n->attributes) as $a) {
			$att = strtolower($a->nodeName);
			$garde = in_array($att, HTML_ATTRIBUTS, true)
			      && (!in_array($att, array('href', 'src'), true) || html_url_sure($a->nodeValue, $att));
			if (!$garde) $n->removeAttribute($a->nodeName);
		}
		if ($nom === 'a' && $n->hasAttribute('href')) $n->setAttribute('rel', 'noopener noreferrer');
	}
}

function assainit_html($html) {
	$html = (string)$html;
	if (trim($html) === '') return '';
	// Sans l'extension dom : texte seul
	if (!class_exists('DOMDocument')) return '<p>'.htmlspecialchars(trim(strip_tags($html)), ENT_QUOTES).'</p>';
	$doc = new DOMDocument();
	$erreurs = libxml_use_internal_errors(true);
	// La déclaration d'encodage fait lire le texte en UTF-8 ; le div sert de racine
	$ok = $doc->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>',
	                     LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
	libxml_clear_errors();
	libxml_use_internal_errors($erreurs);
	$racine = $ok ? $doc->getElementsByTagName('div')->item(0) : null;
	if (is_null($racine)) return '';
	html_assainit_noeud($racine);
	$sortie = '';
	foreach ($racine->childNodes as $n) $sortie .= $doc->saveHTML($n);
	return $sortie;
}

// Nom de fichier de base acceptable (em.db), sans chemin
function bases_nom_valide($fichier) {
	return is_string($fichier) && preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,99}\.db$/D', $fichier) === 1;
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
	$racine = bases_racine($conf);
	if (is_null($racine)) return false;
	$tout_ok = true;
	$presentes = array();
	foreach ($bases as $fichier => $b) {
		if (!bases_nom_valide($fichier) || !is_array($b) || !is_string($b['sha256'] ?? null)
		    || !preg_match('/^[0-9a-f]{64}$/', $b['sha256']) || (int)($b['taille'] ?? 0) > BASES_TAILLE_MAX) continue;
		$nom = substr($fichier, 0, -3);
		$presentes[$nom] = true;
		$dossier = $racine.'/'.$nom;
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
		// Markdown d'origine : texte de la description donné au modèle d'IA (ia_description_base)
		@file_put_contents($dossier.'/description.md', is_string($d['markdown'] ?? null) ? $d['markdown'] : '');
		@file_put_contents($meta_fic.'.tmp', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
		@rename($meta_fic.'.tmp', $meta_fic);
	}
	// Bases retirées de QueryCraft : à la corbeille, seulement sur un catalogue non vide
	if (count($presentes) > 0) {
		$corbeille = $racine.'/.corbeille';
		foreach (bases_dossiers($conf) as $nom => $dossier) {
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

// Vrai si une base synchronisée n'a pas encore de description.md (synchronisée par une version
// qui ne gardait que le HTML)
function bases_sans_markdown($conf) {
	foreach (bases_dossiers($conf) as $dossier) if (!is_file("$dossier/description.md")) return true;
	return false;
}

// Dossiers des bases synchronisées complètes : nom => dossier
function bases_dossiers($conf = null) {
	$liste = array();
	$racine = bases_racine($conf);
	if (is_null($racine)) return $liste;
	foreach ((array)glob($racine.'/*', GLOB_ONLYDIR) as $dossier) {
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
			'description' => assainit_html((string)@file_get_contents("$dossier/description.html")),
			'tables' => array(),
		);
	}
	return count($liste) > 0 ? $liste : null;
}

