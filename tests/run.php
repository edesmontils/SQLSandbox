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
list($r, $o) = execute("insert into big select printf('%.*c', 1000000, 'x') from (with recursive r(n) as "
                     ."(select 1 union all select n+1 from r limit 100) select n from r)");
clearstatcache();
verifie('taille de la base limitée', $r === null && strpos($o, 'taille maximale') !== false
                                    && filesize($copie) <= TAILLE_MAX_BASE, $o);
foreach (array("create temp table tt(a)", "create temp view tv as select 1",
               "create temp trigger tg after insert on t begin select 1; end") as $q) {
	list($r, $o) = execute($q);
	verifie("refusé : $q", $r === null && strpos($o, 'not authorized') !== false, $o);
}
foreach (array("select length(printf('%.*c', 500000000, 'x'))", "select length(randomblob(400000000))") as $q) {
	list($r, $o) = execute($q);
	verifie("mémoire de SQLite limitée : $q", $r === null && strpos($o, 'out of memory') !== false, $o);
}
list($r, $o) = execute("select hex(zeroblob(4)), length(randomblob(16))");
verifie('randomblob() et zeroblob() utilisables', $r === true && strpos($o, '<td>00000000</td><td>16</td>') !== false, $o);
touch($copie, time() - 4 * 3600);
clearstatcache();
execute("select 1");
clearstatcache();
verifie('date de la copie rafraîchie à chaque ouverture', filemtime($copie) > time() - 60);

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
verifie('données de question encodées dans l\'attribut', preg_match("/onClick='ouvre_question\\(([^']*)\\);/", $o, $m)
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
		if (preg_match('#^Set-Cookie: (PHPSESSID=[^;]+)#i', $h, $m)) $cookie = $m[1];
	}
	return array($code, (string)$corps);
}
// Serveur lancé avec -t $racine : son dossier de travail est à côté de la racine web
$travail = dirname($racine).'/sqlsandbox-tmp';
function copies() { global $travail; return count(glob("$travail/copie_*")); }
function tailles() { global $travail; return array_sum(array_map('filesize', glob("$travail/copie_*"))); }

$avant = copies();
list($code, $page) = http("$u/index.php");
$entetes = implode("\n", $derniers_entetes);
verifie('en-têtes de sécurité', stripos($entetes, 'X-Frame-Options: SAMEORIGIN') !== false
                                && stripos($entetes, 'X-Content-Type-Options: nosniff') !== false, $entetes);
verifie('index.php répond', $code == 200 && strpos($page, 'csrf_token') !== false);
// Serveur lancé avec -t $racine : les sessions vont à côté de la racine web, hors du site
$session = dirname($racine).'/sessions-sqlsandbox/sess_'.substr($cookie, strlen('PHPSESSID='));
verifie('session rangée hors du site', is_file($session) && strpos(realpath($session), $racine.'/') !== 0, $session);
verifie('chemins serveur non exposés', strpos($page, '"fichier"') === false && strpos($page, '"config"') === false);
verifie('visite : fichiers réservés hors du site, mais vides', copies() > $avant && tailles() == 0, tailles());
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
list($code, $corps) = http("$u/controler.php", array('Soumettre' => 'end', 'csrf' => $jeton));
verifie('fin de session : copies purgées', strpos($corps, 'purgées') !== false && copies() == $avant);

proc_terminate($serveur);
proc_close($serveur);
foreach (glob("$tmp/*") as $f) unlink($f);
// Verrous et session créés par les tests
foreach (array_merge(glob(DOSSIER_TMP.'/verrou_*'), glob("$travail/verrou_*")) as $f) unlink($f);
if (is_file($session)) unlink($session);
rmdir($tmp);

echo "\n".($total - $echecs)."/$total tests réussis\n";
exit($echecs == 0 ? 0 : 1);
