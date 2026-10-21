<?php 

require './session.php';

if (isset($_SESSION['debug']) && $session_ok) {
	$debug = $_SESSION['debug'];
	if ($debug) ini_set('display_errors', '1');
	require_once('./lib/LibXML.php');
	include "./fonctions.php";

	$Soumettre = isset($_REQUEST['Soumettre']) ? param('Soumettre') : 'rien';
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
	else {//echo "<p>liste base $Soumettre</p>";
		liste_base($Soumettre);
	}
} else {//Envoyer sur index.php5 ?
	echo "<div class='post'><h2 class='title'>Problème d'initialisation ou de session</h2><h3 class='posted'>E. Desmontils</h3><div class='story'>";
	echo "<p>Veuillez relancer l'application (éventuellement en relancant le navigateur).</p>";
	echo "</div></div>\n";
}
?>