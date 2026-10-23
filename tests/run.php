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
// Requêtes sur le schéma : données du diagramme avant/après jointes au résultat
list($r, $o) = execute("create table t2(a primary key, b references t(a))");
verifie('schéma : CREATE TABLE joint le diagramme avant/après', $r === true && strpos($o, 'class="avant-apres"') !== false
        && strpos($o, 'data-avant=') !== false && strpos($o, 't2') !== false, $o);
list($r, $o) = execute("select * from t2");
verifie('schéma : un SELECT ne joint pas de diagramme', strpos($o, 'avant-apres') === false, $o);
list($r, $o) = execute("create index i_t2 on t2(b)");
verifie('schéma : un CREATE INDEX (tables inchangées) ne joint pas de diagramme', $r === true && strpos($o, 'avant-apres') === false, $o);
list($r, $o) = execute("drop table t2");
verifie('schéma : DROP TABLE joint le diagramme', strpos($o, 'class="avant-apres"') !== false, $o);
// Modèle d'origine et retour à la base d'origine
$_SESSION['originaux']['test'] = './bdd/cours/cours';
execute("create table t3(a)");
$m = modele_base('test');
$orig = modele_origine('test', $m);
verifie('origine : modèle d\'origine fourni quand le schéma a changé', !is_null($orig)
        && count($orig['tables']) == count($m['tables']) - 2, json_encode(array_column($m['tables'], 'nom')));
verifie('origine : réinitialisation réussie', reinitialise_base('test') === true);
$m = modele_base('test');
verifie('origine : après réinitialisation, plus de table ajoutée et pas de modèle d\'origine distinct',
        !in_array('t3', array_column($m['tables'], 'nom')) && is_null(modele_origine('test', $m)));
execute("create table t(a)"); // table utilisée par les tests suivants
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

echo "Génération par IA (faux ProxyIAg)\n";
$port_faux = 19000 + getmypid() % 1000;
$reponse_faux = "$tmp/faux_reponse";
function faux($code, $contenu, $fin = 'stop') {
	global $reponse_faux;
	$corps = is_array($contenu) || is_null($contenu) ? json_encode($contenu) : $contenu;
	if ($code == 200) {
		$corps = json_encode(array('choices' => array(array('message' => array('content' => $corps), 'finish_reason' => $fin)),
		                           'usage' => array('total_tokens' => 123)));
	}
	file_put_contents($reponse_faux, "$code\n$corps");
}
file_put_contents("$tmp/ia.ini", "url = \"http://127.0.0.1:$port_faux/v1\"\ncle = \"sk-cle-de-test\"\nmodele = \"proxyiag/test\"\n"
                  ."delai = 0\npar_session = 30\nplafond_global = 0\nlignes_max = 5\n");
putenv("SQLSANDBOX_IA_INI=$tmp/ia.ini");
$env_serveurs = array_merge(getenv(), array('FAUX_REPONSE' => $reponse_faux, 'SQLSANDBOX_IA_INI' => "$tmp/ia.ini"));
$faux_serveur = proc_open(array(PHP_BINARY, '-S', "127.0.0.1:$port_faux", "$racine/tests/faux_proxy.php"),
                          array(array('file', '/dev/null', 'r'), array('file', '/dev/null', 'w'), array('file', '/dev/null', 'w')),
                          $tubes_faux, $racine, $env_serveurs);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port_faux); $i++) usleep(100000);
$compteur_existait = is_file(DOSSIER_TMP.'/ia_compteur');
$conf = ia_config();
verifie('IA : configuration lue', is_array($conf) && $conf['modele'] === 'proxyiag/test' && $conf['lignes_max'] == 5, json_encode($conf));
verifie('IA : fichier absent -> fonction désactivée', is_null(ia_lit_ini("$tmp/inexistant.ini")));
file_put_contents("$tmp/ia2.ini", "url = \"http://serveur-distant.example/v1\"\ncle = \"sk-x\"\nmodele = \"m\"\n");
verifie('IA : clé jamais envoyée en HTTP vers une autre machine', is_null(ia_lit_ini("$tmp/ia2.ini")));
file_put_contents("$tmp/ia2.ini", "url = \"https://serveur-distant.example/v1/\"\ncle = \"sk-x\"\nmodele = \"m\"\n");
$c2 = ia_lit_ini("$tmp/ia2.ini");
verifie('IA : HTTPS accepté, valeurs par défaut', $c2 && $c2['url'] === 'https://serveur-distant.example/v1'
        && $c2['par_session'] == IA_PAR_SESSION && $c2['lignes_max'] == IA_LIGNES_MAX);
file_put_contents("$tmp/ia2.ini", "url = \"https://serveur-distant.example/v1\"\ncle = \"\"\nmodele = \"m\"\n");
verifie('IA : clé vide -> désactivée', is_null(ia_lit_ini("$tmp/ia2.ini")));
unlink("$tmp/ia2.ini");

$copie_ia = "$tmp/copie_ia";
copy('./bdd/cours/cours', $copie_ia);
$_SESSION['copie_ia'] = $copie_ia;
unset($_SESSION['ia']);
function nb_etudiants() { global $copie_ia; $d = new SQLite3($copie_ia); $n = $d->querySingle('select count(*) from etudiants'); $d->close(); return $n; }
$cols = array('noetu', 'nom', 'prenom');
$lignes_ok = array(
	array('noetu' => '11111A', 'nom' => 'Durand', 'prenom' => 'Zoé'),
	array('noetu' => '22222B', 'nom' => 'Leroy', 'prenom' => 'Hugo'),
	array('noetu' => '28936E', 'nom' => 'Doublon', 'prenom' => 'Clé'),   // clé primaire déjà prise
	array('noetu' => '33333C', 'nom' => null, 'prenom' => 'Sans nom'),  // nom NOT NULL
	array('noetu' => '44444D', 'nom' => 'Petit', 'prenom' => 'Léa'),
	array('noetu' => '55555E', 'nom' => 'En trop', 'prenom' => 'X'));
faux(200, array('lignes' => $lignes_ok));
$r = ia_generer('ia', 'etudiants', 'ajouter', $cols, 5, 'des prénoms bretons');
verifie('IA : aperçu produit', isset($r['ok']) && count($r['lignes']) == 5 && $r['ignorees'] == 1, json_encode($r));
$etats = array_column($r['lignes'], 'ok');
verifie('IA : lignes contrôlées (clé en double et NOT NULL refusés)', $etats === array(true, true, false, false, true), json_encode($etats));
verifie('IA : message de contrainte conservé', strpos($r['lignes'][2]['erreur'], 'UNIQUE') !== false
        && strpos($r['lignes'][3]['erreur'], 'NOT NULL') !== false, json_encode(array_column($r['lignes'], 'erreur')));
verifie('IA : l\'aperçu n\'écrit rien dans la base', nb_etudiants() == 6, nb_etudiants());
$requete_faux = (string)file_get_contents($reponse_faux.'.requete');
verifie('IA : clé envoyée au proxy', strpos($requete_faux, 'Bearer sk-cle-de-test') === 0);
verifie('IA : schéma, consigne et clés existantes dans le prompt', strpos($requete_faux, 'CREATE TABLE etudiants') !== false
        && strpos($requete_faux, 'prénoms bretons') !== false && strpos($requete_faux, '28936E') !== false, substr($requete_faux, 0, 400));
verifie('IA : la clé n\'est pas renvoyée au navigateur', strpos(json_encode($r), 'sk-cle-de-test') === false);
verifie('IA : générations restantes comptées', $r['restantes'] == 29, $r['restantes']);
$bonnes = array();
foreach ($r['lignes'] as $l) if ($l['ok']) $bonnes[] = $l['valeurs'];
$w = ia_appliquer('ia', 'etudiants', 'ajouter', $cols, $bonnes);
verifie('IA : écriture des lignes validées', $w['ok'] && $w['ecrites'] == 3 && nb_etudiants() == 9, json_encode($w));
$w = ia_appliquer('ia', 'etudiants', 'ajouter', $cols, array($lignes_ok[2], $lignes_ok[0]));
verifie('IA : à l\'écriture, une ligne refusée n\'empêche pas les autres', $w['ecrites'] == 0 && count($w['refusees']) == 2, json_encode($w)); // 11111A existe déjà
$w = ia_appliquer('ia', 'etudiants', 'ajouter', $cols, array_fill(0, 6, $lignes_ok[0]));
verifie('IA : trop de lignes refusé', isset($w['erreur']) && $w['statut'] == 400, json_encode($w));

// Clés étrangères : les valeurs possibles des tables référencées sont dans le prompt, et contrôlées
faux(200, array('lignes' => array(
	array('noe' => '11111A', 'codemat' => 'MIAS2I5', 'noteex' => 12, 'notecc' => 14),
	array('noe' => 'inconnu', 'codemat' => 'MIAS2I5', 'noteex' => 12, 'notecc' => 14),
	array('noe' => '11111A', 'codemat' => 'MIAS2I6', 'noteex' => 25, 'notecc' => 3))));
$r = ia_generer('ia', 'notes', 'ajouter', array('noe', 'codemat', 'noteex', 'notecc'), 3, '');
verifie('IA : clé étrangère et CHECK contrôlés', array_column($r['lignes'], 'ok') === array(true, false, false), json_encode($r));
$requete_faux = (string)file_get_contents($reponse_faux.'.requete');
verifie('IA : valeurs des tables référencées dans le prompt', strpos($requete_faux, 'MIAS2I5') !== false && strpos($requete_faux, '28936E') !== false);

// Compléter une colonne ajoutée par ALTER TABLE
$d = new SQLite3($copie_ia); $d->exec('ALTER TABLE etudiants ADD COLUMN email varchar(40)'); $d->close();
faux(200, array('lignes' => array(
	array('noetu' => '28936E', 'email' => 'franck.dupont@exemple.fr'),
	array('noetu' => 'absent', 'email' => 'x@exemple.fr'),
	array('noetu' => '11111A', 'email' => array('liste')))));
$r = ia_generer('ia', 'etudiants', 'modifier', array('email', 'noetu'), 3, '');
verifie('IA : compléter -> la clé primaire n\'est jamais une colonne à remplir', isset($r['ok']) && $r['ecrire'] === array('email')
        && $r['colonnes'] === array('noetu', 'email'), json_encode($r));
verifie('IA : compléter -> ligne inconnue et valeur non simple refusées', array_column($r['lignes'], 'ok') === array(true, false, false)
        && strpos($r['lignes'][1]['erreur'], 'identifiant') !== false && strpos($r['lignes'][2]['erreur'], 'non simple') !== false, json_encode($r['lignes']));
$requete_faux = (string)file_get_contents($reponse_faux.'.requete');
verifie('IA : compléter -> les lignes existantes sont envoyées', strpos($requete_faux, 'Dupont') === false && strpos($requete_faux, '28936E') !== false);
$w = ia_appliquer('ia', 'etudiants', 'modifier', array('email'), array(array('noetu' => '28936E', 'email' => 'franck.dupont@exemple.fr')));
$d = new SQLite3($copie_ia);
verifie('IA : compléter -> colonne remplie', $w['ecrites'] == 1 && $d->querySingle("select email from etudiants where noetu = '28936E'") === 'franck.dupont@exemple.fr', json_encode($w));
$d->exec("update etudiants set email = 'x'");
$d->close();
$r = ia_generer('ia', 'etudiants', 'modifier', array('email'), 3, '');
verifie('IA : compléter -> rien à faire si plus de valeur vide', isset($r['erreur']) && $r['statut'] == 400 && strpos($r['erreur'], 'rien à compléter') !== false, json_encode($r));

// Formes de réponse acceptées
$un = array('noetu' => '77777G', 'nom' => 'Texte', 'prenom' => 'Autour');
foreach (array('liste seule' => json_encode(array($un)), 'avec texte autour' => "Voici :\n```json\n".json_encode(array('lignes' => array($un)))."\n```\nBonne chance",
               'autre clé' => json_encode(array('etudiants' => array($un)))) as $nom => $texte) {
	faux(200, $texte);
	$r = ia_generer('ia', 'etudiants', 'ajouter', $cols, 1, '');
	verifie("IA : réponse acceptée ($nom)", isset($r['ok']) && count($r['lignes']) == 1 && $r['lignes'][0]['ok'], json_encode($r));
}
faux(200, 'désolé, je ne peux pas');
$avant = ia_restantes(ia_config());
$r = ia_generer('ia', 'etudiants', 'ajouter', $cols, 1, '');
verifie('IA : réponse inutilisable signalée', isset($r['erreur']) && $r['statut'] == 502, json_encode($r));
faux(200, array('lignes' => array($un)), 'length');
$r = ia_generer('ia', 'etudiants', 'ajouter', $cols, 1, '');
verifie('IA : réponse tronquée signalée', isset($r['erreur']) && $r['statut'] == 502, json_encode($r));

// Pannes du proxy : message sans détail technique, et la génération est rendue
$avant = ia_restantes(ia_config());
foreach (array(array(500, 503), array(429, 429), array(401, 503), array(403, 503)) as $cas) {
	faux($cas[0], '{"detail":"secret technique sk-interne"}');
	$r = ia_generer('ia', 'etudiants', 'ajouter', $cols, 1, '');
	verifie("IA : proxy en code {$cas[0]} -> statut {$cas[1]}, sans détail technique", isset($r['erreur']) && $r['statut'] == $cas[1]
	        && strpos($r['erreur'], 'secret') === false && strpos($r['erreur'], 'sk-') === false, json_encode($r));
}
verifie('IA : générations rendues après une panne', ia_restantes(ia_config()) == $avant, ia_restantes(ia_config()).' / '.$avant);
// 400 : le proxy refuse response_format, la demande est refaite sans
faux(400, '{"detail":"response_format non supporté"}');
$r = ia_generer('ia', 'etudiants', 'ajouter', $cols, 1, '');
verifie('IA : 400 du proxy signalé comme indisponibilité', isset($r['erreur']) && $r['statut'] == 503);

// Paramètres refusés
foreach (array(array('etudiants"; drop table notes; --', 'ajouter', $cols), array('inconnue', 'ajouter', $cols),
               array('etudiants', 'supprimer', $cols), array('etudiants', 'ajouter', array('absente')),
               array('etudiants', 'ajouter', 'noetu'), array('etudiants', 'ajouter', array())) as $i => $p) {
	$r = ia_generer('ia', $p[0], $p[1], $p[2], 1, '');
	verifie("IA : paramètre invalide refusé ($i)", isset($r['erreur']) && in_array($r['statut'], array(400, 404)), json_encode($r));
}
$r = ia_generer('base-inconnue', 'etudiants', 'ajouter', $cols, 1, '');
verifie('IA : base inconnue refusée', isset($r['erreur']) && $r['statut'] == 404);
$d = new SQLite3($copie_ia); $d->exec('CREATE TABLE sans_cle(a, b)'); $d->close();
$r = ia_generer('ia', 'sans_cle', 'modifier', array('a'), 1, '');
verifie('IA : compléter une table sans clé primaire refusé', isset($r['erreur']) && strpos($r['erreur'], 'clé primaire') !== false, json_encode($r));
verifie('IA : base intacte après les refus (hors lignes validées)', nb_etudiants() == 9, nb_etudiants());

// Quotas
$conf = ia_config();
$_SESSION['ia'] = array('jour' => date('Y-m-d'), 'n' => 0, 'dernier' => 0);
faux(200, array('lignes' => array($un)));
for ($i = 0; $i < $conf['par_session']; $i++) ia_generer('ia', 'etudiants', 'ajouter', $cols, 1, '');
$r = ia_generer('ia', 'etudiants', 'ajouter', $cols, 1, '');
verifie('IA : quota par session', isset($r['erreur']) && $r['statut'] == 429 && strpos($r['erreur'], 'générations du jour') !== false, json_encode($r));
$_SESSION['ia'] = array('jour' => date('Y-m-d', time() - 86400), 'n' => 99, 'dernier' => 0);
verifie('IA : le quota repart le lendemain', is_null(ia_reserve($conf)) && $_SESSION['ia']['n'] == 1);
$_SESSION['ia']['dernier'] = time();
$m = ia_reserve(array_merge($conf, array('delai' => 100)));
verifie('IA : délai minimum entre deux générations', is_string($m) && strpos($m, 'Patientez') === 0, $m);
$compteur_avant = is_file(DOSSIER_TMP.'/ia_compteur') ? file_get_contents(DOSSIER_TMP.'/ia_compteur') : null;
@unlink(DOSSIER_TMP.'/ia_compteur');
verifie('IA : plafond global', ia_compteur_global(1, 2) && ia_compteur_global(1, 2) && !ia_compteur_global(1, 2)
        && ia_compteur_global(-1, 2) && ia_compteur_global(1, 2));
verifie('IA : plafond global à 0 = sans limite', ia_compteur_global(1, 0) && ia_compteur_global(1, 0));
$_SESSION['ia'] = array('jour' => date('Y-m-d'), 'n' => 0, 'dernier' => 0);
@unlink(DOSSIER_TMP.'/ia_compteur');
ia_compteur_global(1, 1);
$m = ia_reserve(array_merge($conf, array('plafond_global' => 1)));
verifie('IA : refus quand le plafond global est atteint', is_string($m) && strpos($m, 'application') !== false && $_SESSION['ia']['n'] == 0, $m);
@unlink(DOSSIER_TMP.'/ia_compteur');
if (!is_null($compteur_avant)) file_put_contents(DOSSIER_TMP.'/ia_compteur', $compteur_avant);
unset($_SESSION['ia'], $_SESSION['copie_ia']);
@unlink($copie_ia);

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
                           array('file', '/dev/null', 'w')), $tubes, $racine, $env_serveurs);
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
// Durée de vie : une seule source (config.xml), cookie prolongé, rechargement sans perte
$conf = new SimpleXMLElement('./config.xml', LIBXML_DTDATTR|LIBXML_DTDLOAD|LIBXML_DTDVALID|LIBXML_NOBLANKS|LIBXML_NOCDATA, true);
verifie('durée de vie : identique au seuil de config.xml', duree_vie() === (int)$conf->seuil && duree_vie() > 0, duree_vie().' / '.$conf->seuil);
http("$u/controler.php", array('Soumettre' => 'Envoyer', 'base' => 'cours', 'mode' => '1', 'csrf' => $jeton,
                               'requete' => 'create table rechargement(a)'));
$nb_copies = copies();
list($code, $page2) = http("$u/index.php");
$entetes = implode("\n", $derniers_entetes);
verifie('rechargement : cookie avec date d\'expiration', preg_match('/^Set-Cookie: '.INSTANCE.'=[^\n]*expires=/mi', $entetes) === 1, $entetes);
verifie('rechargement : mêmes copies', copies() == $nb_copies, copies().' / '.$nb_copies);
verifie('rechargement : même jeton', strpos($page2, '"'.$jeton.'"') !== false);
list($code, $corps) = http("$u/controler.php", array('Soumettre' => 'Envoyer', 'base' => 'cours', 'mode' => '1',
                           'csrf' => $jeton, 'requete' => 'select * from rechargement'));
verifie('rechargement : modifications de la base conservées', $code == 200 && strpos($corps, 'Requête réussie') !== false, $corps);
// Génération par IA à travers l'application
verifie('IA : bouton activé dans la page', strpos($page2, 'ia_disponible = true') !== false && strpos($page2, 'js/ia.js') !== false);
$ia = array('Soumettre' => 'ia_generer', 'base' => 'cours', 'table' => 'etudiants', 'mode' => 'ajouter',
            'colonnes' => json_encode($cols), 'nb' => '2', 'consigne' => '');
list($code, $corps) = http("$u/controler.php", $ia);
verifie('IA : génération sans jeton refusée (403)', $code == 403 && !empty(json_decode($corps, true)['erreur']), $corps);
faux(200, array('lignes' => array(array('noetu' => '90001A', 'nom' => 'Via', 'prenom' => 'Web'), array('noetu' => '28936E', 'nom' => 'Dup', 'prenom' => 'X'))));
list($code, $corps) = http("$u/controler.php", $ia + array('csrf' => $jeton));
$j = json_decode($corps, true);
verifie('IA : génération via l\'application', $code == 200 && !empty($j['ok']) && array_column($j['lignes'], 'ok') === array(true, false), $corps);
list($code, $corps) = http("$u/controler.php", array('Soumettre' => 'ia_appliquer', 'base' => 'cours', 'table' => 'etudiants', 'mode' => 'ajouter',
                           'colonnes' => json_encode($cols), 'lignes' => json_encode(array($j['lignes'][0]['valeurs']))));
verifie('IA : écriture sans jeton refusée (403)', $code == 403);
list($code, $corps) = http("$u/controler.php", array('Soumettre' => 'ia_appliquer', 'base' => 'cours', 'table' => 'etudiants', 'mode' => 'ajouter',
                           'csrf' => $jeton, 'colonnes' => json_encode($cols), 'lignes' => json_encode(array($j['lignes'][0]['valeurs']))));
verifie('IA : écriture via l\'application', $code == 200 && json_decode($corps, true)['ecrites'] == 1, $corps);
list($code, $corps) = http("$u/controler.php", array('Soumettre' => 'Envoyer', 'base' => 'cours', 'mode' => '1', 'csrf' => $jeton,
                           'requete' => "select prenom from etudiants where noetu = '90001A'"));
verifie('IA : ligne visible par une requête SQL', strpos($corps, '<td>Web</td>') !== false, $corps);
list($code, $corps) = http("$u/controler.php", array('Soumettre' => 'end', 'csrf' => $jeton));
verifie('fin de session : copies purgées', strpos($corps, 'purgées') !== false && copies() == 0);

proc_terminate($serveur);
proc_close($serveur);
proc_terminate($faux_serveur);
proc_close($faux_serveur);
@unlink($reponse_faux.'.requete');
if (!$compteur_existait) { @unlink(DOSSIER_TMP.'/ia_compteur'); @unlink("$travail/ia_compteur"); }
foreach (glob("$tmp/modeles/*") as $f) unlink($f);
@rmdir("$tmp/modeles");
foreach (glob("$tmp/*") as $f) unlink($f);
// Verrous et session créés par les tests
foreach (array_merge(glob(DOSSIER_TMP.'/verrou_*'), glob("$travail/verrou_*")) as $f) unlink($f);
if (is_file($session)) unlink($session);
rmdir($tmp);

echo "\n".($total - $echecs)."/$total tests réussis\n";
exit($echecs == 0 ? 0 : 1);
