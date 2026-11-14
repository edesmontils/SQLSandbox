<?php 

require './session.php';

// Rubriques affichées telles quelles depuis config.xml : valeur de Soumettre => élément
const RUBRIQUES_CONFIG = array('aides' => 'aides', 'rien' => 'accueil', 'SQL' => 'aPropos', 'credits' => 'credits');

// Section de « À propos de SQL et SQLite » calculée sur le serveur : ce que permet la
// version de SQLite installée (chaque fonctionnalité est essayée sur une base en mémoire)
// et les limites propres à SQL Sandbox
function fonctionnalites_sqlite() {
	$recent = sqlite_recent();
	$db = new SQLite3(':memory:');
	$db->exec('CREATE TABLE t(a INTEGER PRIMARY KEY, b TEXT); CREATE TABLE u(a INTEGER, c TEXT)');
	// libellé, requête d'essai, version de SQLite qui l'introduit, refus propre à SQL Sandbox
	$essais = array(
		array('Fonctions de fenêtrage : <code>ROW_NUMBER() OVER (...)</code>, <code>RANK()</code>...',
		      'SELECT row_number() OVER (ORDER BY a) FROM t', '3.25 (3.32.3 dans SQL Sandbox)', !$recent),
		array('<code>RIGHT JOIN</code> et <code>FULL JOIN</code>',
		      'SELECT * FROM t RIGHT JOIN u USING (a) FULL JOIN t AS v USING (a)', '3.39', false),
		array('<code>NULLS FIRST</code> et <code>NULLS LAST</code> dans un tri',
		      'SELECT a FROM t ORDER BY b NULLS LAST', '3.30', false),
		array('Agrégat filtré : <code>COUNT(*) FILTER (WHERE ...)</code>',
		      'SELECT count(*) FILTER (WHERE a > 1) FROM t', '3.30', false),
		array('Alternative <code>iif(condition, si_vrai, si_faux)</code>',
		      'SELECT iif(a > 1, 1, 0) FROM t', '3.32', false),
		array('Fonctions mathématiques : <code>sqrt()</code>, <code>power()</code>, <code>ln()</code>...',
		      'SELECT sqrt(a), power(a, 2) FROM t', '3.35, selon la compilation', false),
		array('<code>ALTER TABLE ... DROP COLUMN</code>',
		      'ALTER TABLE u DROP COLUMN c', '3.35', false),
		array('<code>RETURNING</code> après <code>INSERT</code>, <code>UPDATE</code> ou <code>DELETE</code>',
		      'DELETE FROM t WHERE a = 0 RETURNING *', '3.35', false),
		array('Fonctions JSON : <code>json_extract()</code>, opérateur <code>-&gt;&gt;</code>',
		      "SELECT json_extract('{\"x\":1}', '$.x'), '{\"x\":1}' ->> 'x'", '3.38', false),
		array('<code>concat()</code> et <code>string_agg()</code> (sinon <code>||</code> et <code>group_concat()</code>)',
		      "SELECT concat(b, 'x'), string_agg(b, ', ') FROM t", '3.44', false),
		array('<code>printf()</code> pour mettre en forme un nombre',
		      "SELECT printf('%.2f', a) FROM t", '3.8 (3.32.3 dans SQL Sandbox)', !$recent),
	);
	$h = "<div class='post'><h2 class='title'>Ce que permet la version installée</h2><div class='story'>\n"
	   ."<p>SQL Sandbox utilise ici SQLite <strong>".htmlspecialchars(SQLite3::version()['versionString'])
	   ."</strong>. Les fonctionnalités récentes de SQL ne sont pas disponibles sur les versions plus anciennes : "
	   ."ce tableau indique celles que vous pouvez utiliser sur ce serveur.</p>\n"
	   ."<table class='resultat'><thead><tr><th>Fonctionnalité</th><th>Depuis SQLite</th><th>Ici</th></tr></thead><tbody>\n";
	foreach ($essais as $e) {
		$ok = !$e[3] && @$db->prepare($e[1]) !== false;
		$h .= "<tr><td>$e[0]</td><td>$e[2]</td><td>".($ok ? '✅ oui' : '❌ non')."</td></tr>\n";
	}
	$db->close();
	$duree = round(DUREE_SESSION / 3600, 1);
	$h .= "</tbody></table>\n"
	   ."<h3>Limites de SQL Sandbox</h3><ul>\n"
	   ."<li>Une requête est arrêtée au bout de ".DELAI_MAX." secondes, et seules les ".MAX_LIGNES
	   ." premières lignes d'un résultat sont affichées.</li>\n"
	   ."<li>Votre copie d'une base peut grossir de ".round(CROISSANCE_MAX_BASE / 1048576)." Mo au plus, sans dépasser "
	   .round(TAILLE_MAX_BASE / 1048576)." Mo. Elle est conservée ".str_replace('.', ',', $duree)
	   ." h après votre dernière action.</li>\n"
	   ."<li>Les commandes qui agissent sur le serveur plutôt que sur votre base sont refusées : "
	   ."<code>ATTACH</code>, la plupart des <code>PRAGMA</code> (seuls <code>table_info</code>, "
	   ."<code>index_list</code>, <code>foreign_key_list</code> et leurs variantes restent permis), "
	   ."les tables, vues et déclencheurs temporaires (<code>CREATE TEMP ...</code>) et les tables virtuelles.</li>\n"
	   ."</ul></div></div>\n";
	return $h;
}

if (isset($_SESSION['debug']) && $session_ok) {
	$debug = $_SESSION['debug'];
	if ($debug) ini_set('display_errors', '1');
	require_once('./lib/LibXML.php');
	include "./fonctions.php";
	prolonge_copies();

	$Soumettre = is_null(param('Soumettre')) ? 'rien' : param('Soumettre');
	$requete = requete_recue();
	$base = param('base');
	
	if ($debug) { 
		echo '<p>Mode Debug On</p>';
		if ($session_ok) echo '<p>Session (re-)lancée correctement</p>';
	}
	
	$ok = true;
	$mode = '1';
	
	//======================================================
	// Début du traitement des différentes actions demandées		
	//======================================================
	if ($Soumettre == 'end') { // supprime les $_SESSION["copie_$nom"];
		if (jeton_valide()) {
			purge_db();
			echo "<p>Bases purgées...</p>";
		} else {
			http_response_code(403);
			echo "<p>Session expirée : l'application a sans doute été ouverte ou rechargée dans un autre onglet. Rechargez cette page pour continuer.</p>";
		}
	} 
	//======================================================
	else if ($Soumettre == "Envoyer") {
		$mode = is_null(param('mode')) ? '1' : param('mode');
		if (!jeton_valide()) {
			http_response_code(403);
			echo "<p>Session expirée : l'application a sans doute été ouverte ou rechargée dans un autre onglet. Rechargez cette page pour continuer.</p>";
		} else if (is_string($requete) && is_string($base)){
			$t=get($requete,$base);  
			// Dans un TP (mode 0 : réponse de l'élève, 2 : requête donnée à comprendre), une
			// requête qui s'exécute n'est pas pour autant une bonne réponse : message neutre
			if (is_null($t)) echo "<p class='statut ko' data-execution='ko'>❌ Échec de la requête</p>";
			else if ($mode == '0') echo "<p class='statut neutre' data-execution='ok'>▶️ Requête exécutée sans erreur</p>"
			    ."<p class='astuce'>Cela ne veut pas dire qu'elle répond à la question : vérifiez que son résultat "
			    ."correspond bien à ce qui est demandé.</p>";
			else if ($mode == '2') echo "<p class='statut neutre' data-execution='ok'>▶️ Requête exécutée</p>";
			else echo "<p class='statut ok' data-execution='ok'>✅ Requête réussie</p>";
			           
			if ($mode == '1') 
				echo '<button type="button" class="btn secondaire" id="memoriser" title="Garder cette requête et son résultat dans l\'onglet Mémoire" onclick="remember()">🧺 Mémoriser</button>';
		} else { 
		   echo "<p>Pas de requête ou/et de base proposée !</p>";
		}
	} 
	//======================================================
	// Rubriques tirées de config.xml (aides, accueil, crédits, à propos de SQL)
	else if (isset(RUBRIQUES_CONFIG[$Soumettre])) {
		try {
			foreach (charge_config()->{RUBRIQUES_CONFIG[$Soumettre]}->children() as $c)
				echo $c->asXML();
			if ($Soumettre == 'SQL') echo fonctionnalites_sqlite();
		} catch (Exception $e) {
			echo erreur_config($e);
		}
	}
	//======================================================
	else if ($Soumettre == "news"){
		try {
			$listeMessages = charge_config()->listeMessages->message;
			foreach($listeMessages as $post_message) {
				$att = $post_message->attributes();
				$titre = $att['titre'];
				$post = "-> Le ".$att['date']." par ".$att['auteur'];
				echo "<div class='post'><h2 class='title'>$titre</h2><h3 class='posted'>$post</h3><div class='story'>";
				foreach($post_message->children() as $c )echo $c->asXML();
				echo "</div></div>\n";
			}
		} catch(Exception $e) {
			echo erreur_config($e);
		}
	}
	//======================================================
	// Travaux pratiques (exercices au format QueryCraft) : base et TP vérifiés dans le catalogue
	else if ($Soumettre == "tp") {
		liste_TP(param('nom_base'));
	}
	// Questions d'un TP, en JSON : la navigation entre elles se fait dans la page
	else if ($Soumettre == "questions") {
		$tp = questions_tp(param('nom_base'), param('tp_name'));
		header('Content-Type: application/json; charset=utf-8');
		if (is_null($tp)) {
			http_response_code(404);
			echo json_encode(array('erreur' => 'TP inconnu.'), JSON_UNESCAPED_UNICODE);
		} else {
			echo json_encode($tp, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		}
	}
	//======================================================
	// Correction de l'intention rédigée pour une question « R->I » / « RT->I »
	else if ($Soumettre == "corriger_intention") {
		header('Content-Type: application/json; charset=utf-8');
		if (!jeton_valide()) {
			$r = ia_erreur("Session expirée : l'application a sans doute été ouverte ou rechargée dans un autre onglet. Rechargez cette page pour continuer.", 403);
		} else {
			$r = ia_corriger_intention($base, param('exercice'), param('question'), param('intention'));
		}
		if (isset($r['statut'])) http_response_code($r['statut']);
		echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	}
	//======================================================
	// Modèle d'une base (tables, clés, SQL), en JSON pour le dessin du diagramme
	else if ($Soumettre == "modele") {
		$modele = modele_base(param('nom_base'));
		header('Content-Type: application/json; charset=utf-8');
		if (is_null($modele)) {
			http_response_code(404);
			echo json_encode(array('erreur' => 'Base inconnue ou inaccessible.'));
		} else {
			$modele['origine'] = modele_origine(param('nom_base'), $modele);
			echo json_encode($modele, JSON_UNESCAPED_UNICODE);
		}
	}
	//======================================================
	// Retour à la base d'origine : la copie de l'étudiant est remplacée par l'original
	else if ($Soumettre == "reinitialiser") {
		if (!jeton_valide()) {
			http_response_code(403);
			echo "<p>Session expirée : l'application a sans doute été ouverte ou rechargée dans un autre onglet. Rechargez cette page pour continuer.</p>";
		} else if (reinitialise_base(param('nom_base'))) {
			echo "ok";
		} else {
			http_response_code(404);
			echo "<p>Base inconnue ou inaccessible.</p>";
		}
	}
	//======================================================
	// Génération de lignes par IA : aperçu à blanc (ia_generer), puis écriture (ia_appliquer)
	else if ($Soumettre == "ia_generer" || $Soumettre == "ia_appliquer") {
		header('Content-Type: application/json; charset=utf-8');
		if (!jeton_valide()) {
			$r = ia_erreur("Session expirée : l'application a sans doute été ouverte ou rechargée dans un autre onglet. Rechargez cette page pour continuer.", 403);
		} else if ($Soumettre == "ia_generer") {
			$r = ia_generer($base, param('table'), param('mode'), json_decode((string)param('colonnes'), true),
			                param('nb'), param('consigne'));
		} else {
			$r = ia_appliquer($base, param('table'), param('mode'), json_decode((string)param('colonnes'), true),
			                  json_decode((string)param('lignes'), true));
		}
		if (isset($r['statut'])) http_response_code($r['statut']);
		echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	}
	//======================================================
	// Contenu d'une table de la copie de l'étudiant (volet de la base dans une question de TP)
	else if ($Soumettre == "table") {
		$nb = param('nom_base');
		$nt = param('table');
		if (!is_string($nb) || !isset($_SESSION["copie_$nb"]) || !is_string($nt)
		    || !in_array($nt, get_tables($nb), true)) {
			http_response_code(404);
			echo "<p>Table inconnue.</p>";
		} else {
			get('select * from "'.str_replace('"', '""', $nt).'" order by 1', $nb);
		}
	}
	//======================================================
	// Tables d'une base (les pages de l'application passent par dbListe.php)
	else if ($Soumettre == "tables") {
		liste_base(param('nom_base'));
	}
	//======================================================
	else {
		http_response_code(400);
		echo "<p>Rubrique inconnue.</p>";
	}
} else {
	// Session inconnue du serveur (expirée, cookie effacé...) : code 401, pour que le
	// JavaScript affiche ce message une seule fois au lieu d'une erreur par rubrique
	http_response_code(401);
	echo "<div class='post' id='session-perdue'><h2 class='title'>Session expirée</h2><div class='story'>"
	    ."<p class='message'>Le serveur ne reconnaît plus votre session, ni donc vos copies des bases. "
	    ."Cela arrive quand la page est restée ouverte longtemps sans être utilisée (une session dure "
	    .duree_lisible(DUREE_SESSION).") ou quand le navigateur a effacé ses cookies.</p>"
	    ."<p>Rechargez l'application pour repartir avec des bases neuves. Les requêtes de l'onglet "
	    ."Mémoire seront perdues : copiez d'abord celles que vous voulez garder.</p>"
	    ."<p class='actions'><a class='btn' href='index.php'>🔄 Recharger l'application</a></p>"
	    ."</div></div>\n";
}
?>