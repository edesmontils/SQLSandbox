<?php 

$session_ok = session_start();
setlocale(LC_TIME,'fr_FR');

if (isset($_SESSION['debug']) && $session_ok) {
	$debug = $_SESSION['debug'];
	require_once('./lib/LibXML.php');
	include "./fonctions.php";

	if (isset($_REQUEST['Soumettre'])) $Soumettre = $_REQUEST['Soumettre'];
	else $Soumettre = 'rien';
	if (isset($_REQUEST['requete'])) $requete = $_REQUEST['requete'];
	else $requete = null;
	if (isset($_REQUEST['base'])) $base = $_REQUEST['base'];
	else $base = null;
	
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
		purge_db();
		//unset($_SESSION['debug']);
		//session_unset();
		echo "<p>Bases purgées...</p>";
	} 
	//======================================================
	else if ($Soumettre == "Envoyer") {
		$mode = $_REQUEST['mode'];
		if (isset($requete) && isset($base)){
		    $requete = stripslashes($requete);	
			$t=get($requete,$base);  
			echo ($ok ? "<img src='images/tick_64.png'  width='32' alt='ok' title='ok'/>"
			           :"<img src='images/block_64.png' width='32' alt='ko' title='ko'/>");
			           
			if ($mode == '1') 
				echo '<img src="images/add_64.png" id="memoriser" name="Mémoriser" alt="Mémoriser" title="Mémoriser" width="32" onClick="remember(); 							return false;" style="cursor:pointer"/>';
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
  			echo "<p>erreur<br/>Pb (Exception) ! $e<br/>".$libxml->afficheErreurs()."<br/></p>";
		}	
	}
	//======================================================
	else if ($Soumettre == "rien"){
		try {
			$config = new SimpleXMLElement('./config.xml',
		                                LIBXML_DTDATTR|LIBXML_DTDLOAD|LIBXML_DTDVALID
		                                |LIBXML_NOBLANKS|LIBXML_NOCDATA|LIBXML_NOENT|LIBXML_COMPACT,
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
  			echo "<p>erreur<br/>Pb (Exception) ! $e<br/>".$libxml->afficheErreurs()."<br/></p>";
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
  			echo "<p>erreur<br/>Pb (Exception) ! $e<br/>".$libxml->afficheErreurs()."<br/></p>";
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
  			echo "<p>erreur<br/>Pb (Exception) ! $e<br/>".$libxml->afficheErreurs()."<br/></p>";
		}	
	}
	else if ($Soumettre == "tp"){
		try {
			$nom_base = $_REQUEST['nom_base'];
			liste_TP($nom_base);									
		} catch(Exception $e) {
  			echo "<p>erreur<br/>Pb (Exception) ! $e<br/>".$libxml->afficheErreurs()."<br/></p>";
		}
	}
	else if ($Soumettre == "themes_dispo"){
		try {
			$nom_base = $_REQUEST['nom_base'];
			themes_dispo($nom_base);								
		} catch(Exception $e) {
  			echo "<p>erreur<br/>Pb (Exception) ! $e<br/>".$libxml->afficheErreurs()."<br/></p>";
		}
	}
	else if ($Soumettre == "liste_quest_thema"){
		try {
			$nom_base = $_REQUEST['nom_base'];
			$theme_questions = $_REQUEST['theme_questions'];
			liste_question_thema($nom_base, $theme_questions);								
		} catch(Exception $e) {
  			echo "<p>erreur<br/>Pb (Exception) ! $e<br/>".$libxml->afficheErreurs()."<br/></p>";
		}
	}

	else if($Soumettre == "questions"){
		try {
			$tp_name = $_REQUEST['tp_name'];
			$nom_base = $_REQUEST['nom_base'];
			liste_question($nom_base, $tp_name);								
		} catch(Exception $e) {
  			echo "<p>erreur<br/>Pb (Exception) ! $e<br/>".$libxml->afficheErreurs()."<br/></p>";
		}
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