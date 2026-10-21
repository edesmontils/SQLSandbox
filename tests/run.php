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
list($r, $o) = execute("insert into big select randomblob(1000000) from (with recursive r(n) as "
                     ."(select 1 union all select n+1 from r limit 100) select n from r)");
clearstatcache();
verifie('taille de la base limitée', $r === null && strpos($o, 'taille maximale') !== false
                                    && filesize($copie) <= TAILLE_MAX_BASE, $o);

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

echo "Purge\n";
$_SESSION['copie_autre'] = "$tmp/copie_autre";
touch($_SESSION['copie_autre']);
purge_db();
verifie('purge_db supprime les copies de session', !file_exists($copie) && !file_exists("$tmp/copie_autre"));

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
	global $cookie;
	$entetes = "Cookie: $cookie\r\n";
	if (!is_null($post)) $entetes .= "Content-Type: application/x-www-form-urlencoded\r\n";
	$ctx = stream_context_create(array('http' => array(
		'method' => is_null($post) ? 'GET' : 'POST', 'header' => $entetes,
		'content' => is_null($post) ? '' : http_build_query($post), 'ignore_errors' => true)));
	$corps = @file_get_contents($url, false, $ctx);
	$code = 0;
	foreach ($http_response_header ?? array() as $h) {
		if (preg_match('#^HTTP/\S+ (\d+)#', $h, $m)) $code = (int)$m[1];
		if (preg_match('#^Set-Cookie: (PHPSESSID=[^;]+)#i', $h, $m)) $cookie = $m[1];
	}
	return array($code, (string)$corps);
}
function copies() { return count(glob(dirname(__DIR__).'/tmp/copie_*')); }

$avant = copies();
list($code, $page) = http("$u/index.php");
verifie('index.php répond', $code == 200 && strpos($page, 'csrf_token') !== false);
verifie('chemins serveur non exposés', strpos($page, '"fichier"') === false && strpos($page, '"config"') === false);
verifie('copies créées dans tmp/', copies() > $avant);
preg_match('/csrf_token = "([0-9a-f]+)"/', $page, $m);
$jeton = $m[1] ?? '';
$requete = array('Soumettre' => 'Envoyer', 'base' => 'cours', 'mode' => '1',
                 'requete' => "select nom from etudiants where nom = 'Dupont'");
list($code, $corps) = http("$u/controler.php", $requete);
verifie('requête sans jeton refusée (403)', $code == 403 && strpos($corps, 'Session expirée') !== false);
list($code, $corps) = http("$u/controler.php", $requete + array('csrf' => $jeton));
verifie('requête avec jeton exécutée', $code == 200 && strpos($corps, 'Dupont') !== false, $corps);
list($code, $corps) = http("$u/controler.php?Soumettre=inexistante");
verifie('rubrique inconnue refusée', strpos($corps, 'inconnue') !== false, $corps);
list($code, $corps) = http("$u/controler.php", array('Soumettre' => 'end', 'csrf' => $jeton));
verifie('fin de session : copies purgées', strpos($corps, 'purgées') !== false && copies() == $avant);

proc_terminate($serveur);
proc_close($serveur);
foreach (glob("$tmp/*") as $f) unlink($f);
rmdir($tmp);

echo "\n".($total - $echecs)."/$total tests réussis\n";
exit($echecs == 0 ? 0 : 1);
