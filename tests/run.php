<?php
//============================================================================
// Tests de SQLSandbox, sans dépendance : php tests/run.php
// Partie 1 : fonctions de fonctions.php ; partie 2 : application servie par
// le serveur intégré de PHP (index.php et controler.php).
//============================================================================

$racine = dirname(__DIR__);
chdir($racine);
ini_set('display_errors', '0');
session_start();
include './fonctions.php';

$echecs = 0;
$total = 0;
function verifie($nom, $condition, $detail = '') {
	global $echecs, $total;
	$total++;
	if ($condition) {
		echo "  ok   $nom\n";
	} else {
		$echecs++;
		echo "  ECHEC $nom".($detail !== '' ? " -> $detail" : '')."\n";
	}
}

// Exécute get() sur la copie de test et renvoie [valeur de retour, sortie HTML]
function execute($requete) {
	ob_start();
	$r = get($requete, 'test');
	return array($r, ob_get_clean());
}

$tmp = sys_get_temp_dir().'/sqlsandbox-tests-'.getmypid();
mkdir($tmp);
$copie = "$tmp/copie_test";
copy('./bdd/cours/cours', $copie);
$_SESSION['copie_test'] = $copie;

echo "SQLite ".SQLite3::version()['versionString'].", PHP ".PHP_VERSION."\n";
echo "Exécution des requêtes\n";
list($r, $o) = execute("select nom from etudiants where nom = 'Dupont'");
verifie('apostrophes acceptées', $r === true && strpos($o, '<td>Dupont</td>') !== false, $o);
list($r, $o) = execute("select 1 as a where 1 = 0");
verifie('résultat vide sans erreur', $r === true && strpos($o, '0 ligne(s)') !== false, $o);
execute("create table t(a)");
execute("insert into t values(1)");
$db = new SQLite3($copie);
verifie('INSERT exécuté une seule fois', $db->querySingle("select count(*) from t") == 1);
$db->close();
list($r, $o) = execute("insert into etudiants values(null, null, null)");
verifie('violation de contrainte signalée', $r === null && strpos($o, 'NOT NULL') !== false, $o);
// Clés étrangères vérifiées sur la copie (désactivées par défaut dans SQLite)
list($r, $o) = execute("insert into notes (noe, codemat) values ('inconnu', 'inconnu')");
verifie('clé étrangère : insertion orpheline refusée', $r === null && strpos($o, 'FOREIGN KEY') !== false
        && strpos($o, 'Une clé étrangère') !== false, $o);
list($r, $o) = execute("delete from etudiants where noetu in (select noe from notes)");
verifie('clé étrangère : suppression d\'une ligne référencée refusée', $r === null && strpos($o, 'FOREIGN KEY') !== false, $o);
list($r, $o) = execute("select '<script>x</script>' as \"<b>\"");
verifie('sortie échappée', strpos($o, '<script>x') === false && strpos($o, '&lt;script&gt;') !== false
                           && strpos($o, '<b>') === false, $o);
list($r, $o) = execute("with recursive r(n) as (select 1 union all select n+1 from r limit 5000) select n from r");
verifie('affichage limité à MAX_LIGNES', substr_count($o, '<tr>') == MAX_LIGNES + 1 && strpos($o, 'limité') !== false);
list($r, $o) = execute("select 1");
ob_start(); $r = get('select 1', 'inconnue'); $o = ob_get_clean();
verifie('base inconnue refusée', $r === null && strpos($o, 'inconnue') !== false, $o);

echo "Restrictions de sécurité\n";
$cible = "$tmp/ecrit.php";
foreach (array("attach '$cible' as x", "vacuum into '$cible'", "pragma journal_mode = off",
               "pragma max_page_count = 1000000", "select load_extension('$tmp/x')",
               "select fts3_tokenizer('simple')") as $q) {
	list($r, $o) = execute($q);
	verifie("refusé : $q", $r === null && preg_match('/not authorized|authorization denied/', $o), $o);
}
verifie('aucun fichier écrit hors de la base', !file_exists($cible));
list($r, $o) = execute("pragma table_info(etudiants)");
verifie('PRAGMA table_info autorisé', $r === true && strpos($o, 'noetu') !== false, $o);
list($r, $o) = execute("select name from pragma_table_info('etudiants')");
verifie('pragma_table_info() autorisé', $r === true && strpos($o, 'noetu') !== false, $o);
execute("create table big(b)");
// hex(zeroblob(n)) : chaîne de 2n caractères, disponible quelle que soit la version de SQLite
list($r, $o) = execute("insert into big select hex(zeroblob(500000)) from (with recursive r(n) as "
                     ."(select 1 union all select n+1 from r limit 100) select n from r)");
clearstatcache();
verifie('taille de la base limitée', $r === null && strpos($o, 'taille maximale') !== false
                                    && filesize($copie) <= TAILLE_MAX_BASE, $o);
foreach (array("create temp table tt(a)", "create temp view tv as select 1",
               "create temp trigger tg after insert on t begin select 1; end") as $q) {
	list($r, $o) = execute($q);
	verifie("refusé : $q", $r === null && strpos($o, 'not authorized') !== false, $o);
}
// PRAGMA hard_heap_limit n'existe qu'à partir de SQLite 3.31 : avant, il est ignoré
// (pas de requête de 400 Mo dans ce cas)
if (SQLite3::version()['versionNumber'] >= 3031000) {
	foreach (array("select length(hex(zeroblob(200000000)))", "select length(randomblob(400000000))") as $q) {
		list($r, $o) = execute($q);
		verifie("mémoire de SQLite limitée : $q", $r === null && strpos($o, 'out of memory') !== false, $o);
	}
} else {
	echo "  --   mémoire de SQLite non limitée par cette version (".SQLite3::version()['versionString']."), test ignoré\n";
}
list($r, $o) = execute("select hex(zeroblob(4)), length(randomblob(16))");
verifie('randomblob() et zeroblob() utilisables', $r === true && strpos($o, '<td>00000000</td><td>16</td>') !== false, $o);
touch($copie, time() - 4 * 3600);
clearstatcache();
execute("select 1");
clearstatcache();
verifie('date de la copie rafraîchie à chaque ouverture', filemtime($copie) > time() - 60);

echo "Anciennes versions de SQLite\n";
foreach (array("select nom, row_number() over (order by nom) from etudiants" => true,
               "select nom, count(*) over w from etudiants window w as (order by nom)" => true,
               "select nom, rank() OVER(partition by prenom) from etudiants" => true,
               "select over from t where window = 1" => false,
               "select 'over (' from etudiants -- over (" => false) as $q => $attendu) {
	verifie(($attendu ? 'fenêtrage détecté : ' : 'pas de fenêtrage : ').$q, utilise_fenetrage($q) === $attendu);
}
verifie('fenêtrage et printf() refusés avant SQLite 3.32.3 (OVH : 3.27.2)', !sqlite_recent(3027002) && sqlite_recent(3045001));
$version = SQLite3::version()['versionString'];
list($r, $o) = execute("select nom, row_number() over (order by nom) from etudiants");
if (sqlite_recent()) verifie("fenêtrage utilisable avec SQLite $version", $r === true, $o);
else verifie("fenêtrage refusé avec SQLite $version", $r === null && strpos($o, 'fenêtrage') !== false, $o);
foreach (array("select printf('%d', 1)", "select format('%d', 1)") as $q) {
	list($r, $o) = execute($q);
	// format() n'existe qu'à partir de SQLite 3.38
	if (sqlite_recent()) verifie("utilisable avec SQLite $version : $q", $r === true || strpos($o, 'no such function') !== false, $o);
	else verifie("refusé avec SQLite $version : $q", $r === null && preg_match('/not authorized|no such function/', $o), $o);
}
foreach (array("create virtual table v3 using fts3(x)", "create virtual table v4 using fts4(x)") as $q) {
	list($r, $o) = execute($q);
	verifie("refusé : $q", $r === null && strpos($o, 'not authorized') !== false, $o);
}
list($r, $o) = execute("analyze");
verifie('ANALYZE utilisable', $r === true, $o);
foreach (array("update sqlite_stat1 set stat = '1 0'", "insert into sqlite_stat1 values('etudiants', null, '0 0')") as $q) {
	list($r, $o) = execute($q);
	verifie("refusé : $q", $r === null && strpos($o, 'not authorized') !== false, $o);
}
execute("create table d(id integer primary key autoincrement, a)");
list($r, $o) = execute("insert into d(a) values (1)");
verifie('AUTOINCREMENT utilisable (sqlite_sequence)', $r === true, $o);
list($r, $o) = execute("drop table d");
verifie('DROP TABLE utilisable', $r === true, $o);
list($r, $o) = execute("select count(*) from sqlite_master where name = 'd'");
verifie('table bien supprimée', strpos($o, '<td>0</td>') !== false, $o);
list($r, $o) = execute("select '".str_repeat('x', LONGUEUR_MAX_REQUETE)."'");
verifie('requête trop longue refusée', $r === null && strpos($o, 'trop longue') !== false, substr($o, 0, 200));

echo "Requêtes simultanées par adresse IP\n";
// Ce processus a déjà sa place (requêtes ci-dessus) : on simule un autre client
// dont toutes les places sont occupées par d'autres processus.
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$occupes = array();
$prefixe = DOSSIER_TMP.'/verrou_'.md5('203.0.113.7').'_';
for ($i = 0; $i < REQUETES_PAR_IP; $i++) {
	$occupes[$i] = fopen($prefixe.$i, 'c');
	flock($occupes[$i], LOCK_EX);
}
$commande = escapeshellarg(PHP_BINARY).' -r '.escapeshellarg(
	'chdir('.var_export(dirname(__DIR__), true).'); $_SERVER["REMOTE_ADDR"] = "203.0.113.7";'
	.' include "./fonctions.php"; $t = microtime(true); $r = reserve_execution();'
	.' echo json_encode(array($r, round(microtime(true) - $t)));');
list($obtenue, $attente) = json_decode(shell_exec($commande));
verifie('places occupées : refus après l\'attente', $obtenue === false && $attente >= ATTENTE_PLACE, json_encode(array($obtenue, $attente)));
flock($occupes[0], LOCK_UN);
list($obtenue, $attente) = json_decode(shell_exec($commande));
verifie('une place libérée : exécution acceptée', $obtenue === true, json_encode(array($obtenue, $attente)));
foreach ($occupes as $i => $f) { fclose($f); unlink($prefixe.$i); }

echo "Détection de plusieurs instructions\n";
$cas = array(
	array("select 1", false),
	array("select 1;", false),
	array("select 1 ;  -- fin\n", false),
	array("select 'a;b'; /* ; */", false),
	array("select \"x;y\" from [t;u]", false),
	array("create trigger tr after insert on t begin update t set a = case when 1 then 2 end; delete from t; end;", false),
	array("select 1; select 2", true),
	array("select 1; drop table t", true),
	array("create trigger tr after insert on t begin select 1; end; drop table t", true),
);
foreach ($cas as $c) {
	verifie(($c[1] ? 'plusieurs : ' : 'une seule : ').str_replace("\n", ' ', $c[0]),
	        plusieurs_instructions($c[0]) === $c[1]);
}
list($r, $o) = execute("select 1; drop table etudiants");
verifie('avertissement affiché', strpos($o, 'seule la première instruction') !== false, $o);

echo "Travaux pratiques\n";
$_SESSION['configs']['tp-em'] = './bdd/em/config.xml';
$conf = config_base('tp-em');
verifie('configuration lue depuis la session', $conf !== null);
verifie('base sans configuration en session refusée', config_base('../../etc') === null && config_base('cours') === null);
$tp = cherche_tp($conf, 'tp-em-01');
$ids = array_map('id_question', questions_tp($conf, $tp));
verifie('questions du TP dans l\'ordre', $ids === array('tr-em-01', 're-em-01', 'int-em-01', 'cmp-em-01'), implode(',', $ids));
verifie('TP inconnu', cherche_tp($conf, 'inexistant') === null);
verifie('thèmes ouverts et difficulté', themes_ouverts($conf) === array('projection' => 1), json_encode(themes_ouverts($conf)));
$q = cherche_question($conf, 'int-em-01');
verifie('texte SQL reconstruit', texte_sql($q->SQL) === "SELECT nom_f\nFROM Fleuve NATURAL JOIN EspaceMaritime\nWHERE type='mer'", texte_sql($q->SQL));
ob_start(); liste_question('tp-em', '<script>x</script>'); $o = ob_get_clean();
verifie('nom de TP non reconnu non affiché', strpos($o, '<script>x') === false && strpos($o, 'TP inconnu') !== false, $o);
ob_start(); bouton_question(cherche_question($conf, 'tr-em-01'), 'tp-em', 1); $o = ob_get_clean();
verifie('données de question encodées dans l\'attribut', preg_match("/onclick='ouvre_question\\(([^']*)\\)/i", $o, $m)
        && json_decode(html_entity_decode($m[1], ENT_QUOTES))->sql === "SELECT \nFROM Fleuve\nWHERE longueur>1000", $o);

// Chaque requête de référence des fichiers de configuration doit s'exécuter sur sa base
foreach (glob('./bdd/*/config.xml') as $fic) {
	$c = simplexml_load_file($fic, 'SimpleXMLElement', LIBXML_DTDLOAD|LIBXML_DTDATTR);
	if (!isset($c->{'liste-questions'})) continue;
	$bd = new SQLite3(dirname($fic).'/'.(string)$c->fichier['nom'], SQLITE3_OPEN_READONLY);
	foreach ($c->{'liste-questions'}->children() as $q) {
		$sql = texte_sql($q->SQL[0]);
		verifie("requête de référence ".basename(dirname($fic))."/".id_question($q), @$bd->query($sql) !== false,
		        $bd->lastErrorMsg()." : ".str_replace("\n", ' ', $sql));
	}
	$bd->close();
}

echo "Modèle des bases\n";
// Toutes les bases déclarent des clés étrangères et leurs données les respectent
foreach (glob('./bdd/*/config.xml') as $fic) {
	$c = simplexml_load_file($fic);
	$fichier = dirname($fic).'/'.(string)$c->fichier['nom'];
	$copie_modele = "$tmp/copie_modele";
	copy($fichier, $copie_modele);
	$_SESSION['copie_modele'] = $copie_modele;
	$m = modele_base('modele');
	$nb = 0;
	foreach ($m['tables'] as $t) $nb += count($t['cles']);
	$bd = new SQLite3($fichier, SQLITE3_OPEN_READONLY);
	$violations = 0;
	$r = $bd->query('PRAGMA foreign_key_check');
	while ($r->fetchArray()) $violations++;
	$bd->close();
	verifie('clés étrangères de '.basename(dirname($fic)), count($m['tables']) > 0 && $nb > 0 && $violations == 0,
	        count($m['tables'])." tables, $nb clés, $violations violation(s)");
	// Modèle écrit pour tests/modele.js (disposition du diagramme)
	@mkdir("$tmp/modeles");
	file_put_contents("$tmp/modeles/".(string)$c['nom'].'.json', json_encode($m, JSON_UNESCAPED_UNICODE));
	unlink($copie_modele);
}
unset($_SESSION['copie_modele']);
// Le modèle est lu sur la copie de l'étudiant : il suit ses créations de tables
execute("create table modele_essai (a int primary key, b int references etudiants)");
$m = modele_base('test');
$essai = array_values(array_filter($m['tables'], function ($t) { return $t['nom'] == 'modele_essai'; }));
verifie('modèle : table créée par l\'étudiant', count($essai) == 1 && count($essai[0]['cles']) == 1
        && $essai[0]['cles'][0]['table'] == 'etudiants' && $essai[0]['cles'][0]['references'] == array('noetu'),
        json_encode($essai));
verifie('modèle : base inconnue', modele_base('inconnue') === null);
// Diagramme (JavaScript) : testé avec node quand il est installé (ceci est une vérification de
// js/modele.js, que PHP ne peut pas exécuter)
$node = trim((string)@shell_exec('command -v node 2>/dev/null'));
if ($node !== '') {
	$sortie = array();
	exec(escapeshellarg($node).' '.escapeshellarg(__DIR__.'/modele.js').' '.escapeshellarg("$tmp/modeles").' 2>&1', $sortie, $code);
	$ok = array_filter($sortie, function ($l) { return strpos($l, '  ok   ') === 0; });
	$echecs_node = array_filter($sortie, function ($l) { return strpos($l, '  ECHEC ') === 0; });
	verifie('diagramme : '.count($ok).' vérifications (clés composées, cas limites, disposition des bases)',
	        $code == 0 && count($echecs_node) == 0 && count($ok) >= 32, implode(' | ', $echecs_node ?: $sortie));
	// Coloration SQL des requêtes mémorisées (vrai analyseur de CodeMirror, chargé sous node)
	$sortie = array();
	exec(escapeshellarg($node).' '.escapeshellarg(__DIR__.'/coloration.js').' 2>&1', $sortie, $code);
	$ok = array_filter($sortie, function ($l) { return strpos($l, '  ok   ') === 0; });
	$echecs_node = array_filter($sortie, function ($l) { return strpos($l, '  ECHEC ') === 0; });
	verifie('coloration SQL : '.count($ok).' vérifications (mots-clés, chaînes, échappement, texte conservé)',
	        $code == 0 && count($echecs_node) == 0 && count($ok) >= 17, implode(' | ', $echecs_node ?: $sortie));
} else {
	echo "  --   diagramme et coloration : node absent, tests ignorés\n";
}

echo "Purge\n";
$_SESSION['copie_autre'] = "$tmp/copie_autre";
touch($_SESSION['copie_autre']);
purge_db();
verifie('purge_db supprime les copies de session', !file_exists($copie) && !file_exists("$tmp/copie_autre"));
$_SESSION['originaux']['test'] = realpath('./bdd/cours/cours');
list($r, $o) = execute("select count(*) from etudiants");
verifie('copie purgée recréée depuis la base d\'origine', $r === true && file_exists($copie), $o);
unset($_SESSION['originaux']['test']);
unlink($copie);
list($r, $o) = execute("select 1");
verifie('copie introuvable : message explicite', $r === null && strpos($o, 'Rouvrez l\'application') !== false, $o);

// ---------------------------------------------------------------------------
echo "Application (serveur intégré)\n";
$port = 18000 + getmypid() % 1000;
$u = "http://127.0.0.1:$port";
$serveur = proc_open(array(PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $racine),
                     array(array('file', '/dev/null', 'r'), array('file', '/dev/null', 'w'),
                           array('file', '/dev/null', 'w')), $tubes, $racine);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) usleep(100000);

$cookie = '';
function http($url, $post = null) {
	global $cookie, $derniers_entetes;
	$entetes = "Cookie: $cookie\r\n";
	if (!is_null($post)) $entetes .= "Content-Type: application/x-www-form-urlencoded\r\n";
	$ctx = stream_context_create(array('http' => array(
		'method' => is_null($post) ? 'GET' : 'POST', 'header' => $entetes,
		'content' => is_null($post) ? '' : http_build_query($post), 'ignore_errors' => true)));
	$corps = @file_get_contents($url, false, $ctx);
	$code = 0;
	$derniers_entetes = $http_response_header ?? array();
	foreach ($derniers_entetes as $h) {
		if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) $code = (int)$m[1];
		if (preg_match('#^Set-Cookie: ('.INSTANCE.'=[^;]+)#i', $h, $m)) $cookie = $m[1];
	}
	return array($code, (string)$corps);
}
// Serveur lancé avec -t $racine : son dossier de travail est à côté de la racine web
$travail = dirname($racine).'/'.INSTANCE.'-tmp';
// Seuls les fichiers créés pendant les tests comptent : des copies laissées par un essai
// précédent peuvent être supprimées entre-temps par la purge de index.php (plus de 3 h)
$deja_presentes = glob("$travail/copie_*");
function nouvelles() { global $travail, $deja_presentes; clearstatcache(); return array_diff(glob("$travail/copie_*"), $deja_presentes); }
function copies() { return count(nouvelles()); }
function tailles() { return array_sum(array_map('filesize', nouvelles())); }

list($code, $page) = http("$u/index.php");
$entetes = implode("\n", $derniers_entetes);
verifie('en-têtes de sécurité', stripos($entetes, 'X-SQLSandbox: 1') !== false
                                && stripos($entetes, 'X-Frame-Options: SAMEORIGIN') !== false
                                && stripos($entetes, 'X-Content-Type-Options: nosniff') !== false, $entetes);
verifie('index.php répond', $code == 200 && strpos($page, 'csrf_token') !== false);
// Serveur lancé avec -t $racine : les sessions vont à côté de la racine web, hors du site
$session = dirname($racine).'/sessions-'.INSTANCE.'/sess_'.substr($cookie, strlen(INSTANCE.'='));
verifie('session rangée hors du site', is_file($session) && strpos(realpath($session), $racine.'/') !== 0, $session);
verifie('chemins serveur non exposés', strpos($page, '"fichier"') === false && strpos($page, '"config"') === false);
verifie('visite : fichiers réservés hors du site, mais vides', copies() > 0 && tailles() == 0, tailles());
verifie('aucune copie dans tmp/ du site', count(glob($racine.'/tmp/copie_*')) == 0);
preg_match('/csrf_token = "([0-9a-f]+)"/', $page, $m);
$jeton = $m[1] ?? '';
$requete = array('Soumettre' => 'Envoyer', 'base' => 'cours', 'mode' => '1',
                 'requete' => "select nom from etudiants where nom = 'Dupont'");
list($code, $corps) = http("$u/controler.php", $requete);
verifie('requête sans jeton refusée (403)', $code == 403 && strpos($corps, 'Session expirée') !== false);
list($code, $corps) = http("$u/controler.php", $requete + array('csrf' => $jeton));
verifie('requête avec jeton exécutée', $code == 200 && strpos($corps, 'Dupont') !== false, $corps);
verifie('copie faite à la première requête', tailles() > 0, tailles());
list($code, $corps) = http("$u/controler.php?Soumettre=inexistante");
verifie('rubrique inconnue refusée', strpos($corps, 'inconnue') !== false, $corps);
list($code, $corps) = http("$u/tp.php?base=tp-em&tp=tp-em-01");
verifie('page d\'un TP', substr_count($corps, 'ouvre_question(') == 4, $corps);
list($code, $corps) = http("$u/tp.php?base=tp-em&tp=".urlencode('<img src=x>'));
verifie('page d\'un TP : paramètre non affiché', strpos($corps, '<img src=x>') === false && strpos($corps, 'TP inconnu') !== false);
list($code, $corps) = http("$u/tp.php?base=tp-em&tp[]=x");
verifie('paramètre en tableau ignoré sans warning', strpos($corps, 'TP inconnu') !== false && stripos($corps, 'warning') === false, $corps);
list($code, $corps) = http("$u/questionsThem.php?base=tp-em&theme=projection");
verifie('page d\'un thème', substr_count($corps, 'ouvre_question(') == 1, $corps);
list($code, $corps) = http("$u/controler.php?Soumettre=tp&nom_base=tp-em");
verifie('liste des TP', strpos($corps, 'page_tp(') !== false && strpos($corps, 'tp-em-02') !== false, $corps);
list($code, $corps) = http("$u/controler.php?Soumettre=modele&nom_base=tp-cinema");
$m = json_decode($corps, true);
verifie('modèle en JSON', $code == 200 && count($m['tables']) == 5 && count($m['sql']) == 5, $corps);
list($code, $corps) = http("$u/controler.php?Soumettre=modele&nom_base=inconnue");
verifie('modèle d\'une base inconnue refusé (404)', $code == 404, $corps);
list($code, $corps) = http("$u/controler.php", array('Soumettre' => 'end', 'csrf' => $jeton));
verifie('fin de session : copies purgées', strpos($corps, 'purgées') !== false && copies() == 0);

proc_terminate($serveur);
proc_close($serveur);
foreach (glob("$tmp/modeles/*") as $f) unlink($f);
@rmdir("$tmp/modeles");
foreach (glob("$tmp/*") as $f) unlink($f);
// Verrous et session créés par les tests
foreach (array_merge(glob(DOSSIER_TMP.'/verrou_*'), glob("$travail/verrou_*")) as $f) unlink($f);
if (is_file($session)) unlink($session);
rmdir($tmp);

echo "\n".($total - $echecs)."/$total tests réussis\n";
exit($echecs == 0 ? 0 : 1);
