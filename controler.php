<?php 

require './session.php';

if (isset($_SESSION['debug']) && $session_ok) {
	$debug = $_SESSION['debug'];
	if ($debug) ini_set('display_errors', '1');
	require_once('./lib/LibXML.php');
	include "./fonctions.php";
		prolonge_copies();

	$Soumettre = is_null(param('Soumettre')) ? 'rien' : param('Soumettre');
	$requete = param('requete');
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
			echo (!is_null($t) ? "<p class='statut ok'>✅ Requête réussie</p>"
			           : "<p class='statut ko'>❌ Échec de la requête</p>");
			           
			if ($mode == '1') 
				echo '<button type="button" class="btn secondaire" id="memoriser" title="Garder cette requête et son résultat dans l\'onglet Mémoire" onclick="remember()">🧺 Mémoriser</button>';
		} else { 
		   echo "<p>Pas de requête ou/et de base proposée !</p>";
		}
	} 
	//======================================================
	else if ($Soumettre == 'aides') {
		try {
			$config = new SimpleXMLElement('./config.xml',
	                                LIBXML_DTDATTR|LIBXML_DTDLOAD|LIBXML_DTDVALID
	                                |LIBXML_NOBLANKS|LIBXML_NOCDATA,
	                                true);
			foreach($config->aides->children() as $c)
			echo $c->asXML();
		} catch(Exception $e) {
  			echo "<p>erreur<br/>Pb (Exception) ! ".htmlspecialchars($e->getMessage())."<br/>".$libxml->afficheErreurs()."<br/></p>";
		}	
	}
	//======================================================
	else if ($Soumettre == "rien"){
		try {
			$config = new SimpleXMLElement('./config.xml',
		                                LIBXML_DTDATTR|LIBXML_DTDLOAD|LIBXML_DTDVALID
		                                |LIBXML_NOBLANKS|LIBXML_NOCDATA|LIBXML_COMPACT,
		                                true);
			$listeMessages = $config->listeMessages->message;
			foreach($listeMessages as $post_message) {
				$att = $post_message->attributes();
				$titre = $att['titre'];
				$post = "-> Le ".$att['date']." par ".$att['auteur'];
				echo "<div class='post'><h2 class='title'>$titre</h2><h3 class='posted'>$post</h3><div class='story'>";
				foreach($post_message->children() as $c )echo $c->asXML();
				echo "</div></div>\n";
			}
		} catch(Exception $e) {
  			echo "<p>erreur<br/>Pb (Exception) ! ".htmlspecialchars($e->getMessage())."<br/>".$libxml->afficheErreurs()."<br/></p>";
		}	
	}
	//======================================================
	else  if ($Soumettre == "SQL"){
		try {
			$config = new SimpleXMLElement('./config.xml',
		                                LIBXML_DTDATTR|LIBXML_DTDLOAD|LIBXML_DTDVALID
		                                |LIBXML_NOBLANKS|LIBXML_NOCDATA,
		                                true);
			$apropos = $config->aPropos;
			foreach($apropos->children() as $c)
				echo $c->asXML();
		} catch(Exception $e) {
  			echo "<p>erreur<br/>Pb (Exception) ! ".htmlspecialchars($e->getMessage())."<br/>".$libxml->afficheErreurs()."<br/></p>";
		}	
	}
	//======================================================
	else if ($Soumettre == "mentions"){
		try {
			$config = new SimpleXMLElement('./config.xml',
		                                LIBXML_DTDATTR|LIBXML_DTDLOAD|LIBXML_DTDVALID
		                                |LIBXML_NOBLANKS|LIBXML_NOCDATA,
		                                true);
			$mentions = $config->mentions;
			echo '<div class="post"><h2 class="title">Mentions</h2>
				  <h3 class="posted">par E. Desmontils</h3><div class="story">';
			foreach($mentions->children() as $c)
				echo $c->asXML();
			echo "</div></div>\n";										
		} catch(Exception $e) {
  			echo "<p>erreur<br/>Pb (Exception) ! ".htmlspecialchars($e->getMessage())."<br/>".$libxml->afficheErreurs()."<br/></p>";
		}	
	}
	//======================================================
	// Travaux pratiques : les noms de base et de TP sont vérifiés dans la configuration
	else if ($Soumettre == "tp") {
		liste_TP(param('nom_base'));
	}
	else if ($Soumettre == "themes_dispo") {
		themes_dispo(param('nom_base'));
	}
	else if ($Soumettre == "pageIni") {
		page_tp(param('nom_base'), param('tp_name'));
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
	else {//echo "<p>liste base $Soumettre</p>";
		liste_base($Soumettre);
	}
} else {
	// Session inconnue du serveur (expirée, cookie effacé...) : code 401, pour que le
	// JavaScript affiche ce message une seule fois au lieu d'une erreur par rubrique
	http_response_code(401);
	echo "<div class='post' id='session-perdue'><h2 class='title'>Session expirée</h2><div class='story'>"
	    ."<p class='message'>Le serveur ne reconnaît plus votre session, ni donc vos copies des bases. "
	    ."Cela arrive quand la page est restée ouverte longtemps sans être utilisée (une session dure "
	    ."3 heures) ou quand le navigateur a effacé ses cookies.</p>"
	    ."<p>Rechargez l'application pour repartir avec des bases neuves. Les requêtes de l'onglet "
	    ."Mémoire seront perdues : copiez d'abord celles que vous voulez garder.</p>"
	    ."<p class='actions'><a class='btn' href='index.php'>🔄 Recharger l'application</a></p>"
	    ."</div></div>\n";
}
?>