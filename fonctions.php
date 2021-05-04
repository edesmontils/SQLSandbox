<?php

function erreur_fr($erreur) {
global $ok;
  $ok = false;
  switch($erreur) {
    case SQLITE_OK : $mess = "Résultat réussi."; break;
    case SQLITE_ERROR : $mess = "Erreur SQL ou base de données manquante."; break;
    case SQLITE_INTERNAL : $mess = "Une erreur interne de logique dans SQLite."; break;
    case SQLITE_PERM : $mess = "Permission d'accès refusée."; break;
    case SQLITE_ABORT : $mess = "Routine de procédure de rappel a demandé un abandon."; break;
    case SQLITE_BUSY  : $mess = "Le fichier de base de données est verrouillé."; break;
    case SQLITE_LOCKED : $mess = "Une table dans la base de données est verrouillée."; break;
    case SQLITE_NOMEM  : $mess = "Allocation de mémoire échouée."; break;
    case SQLITE_READONLY : $mess = "Essai d'écrire dans une base de données en lecture seule."; break;
    case SQLITE_INTERRUPT : $mess = "Opération terminée de manière interne."; break;
    case SQLITE_IOERR : $mess = "Erreur disque I/O s'est produite."; break;
    case SQLITE_NOTADB : $mess = "Le fichier ouvert n'est pas une base de données."; break;
    case SQLITE_CORRUPT : $mess = "L'image disque de la base de données est malformée."; break;
    case SQLITE_FORMAT : $mess = "Erreur auxiliaire de format de base de données."; break;
    case SQLITE_NOTFOUND : $mess = "(Interne) Table ou enregistrement non trouvé."; break;
    case SQLITE_FULL : $mess = "Insertion échouée car la base de données est pleine."; break;
    case SQLITE_CANTOPEN : $mess = "Impossible d'ouvrir le fichier de base de données."; break;
    case SQLITE_PROTOCOL : $mess = "Erreur du protocole de verrou de base de données."; break;
    case SQLITE_EMPTY : $mess = "(Interne) Une table de la base de données est vide."; break;
    case SQLITE_SCHEMA : $mess = "Le schéma de base de données a changé."; break;
    case SQLITE_TOOBIG : $mess = "Trop de données pour une ligne de la table."; break;
    case SQLITE_CONSTRAINT : $mess = "Arrêt dû à une violation de contrainte."; break;
    case SQLITE_MISMATCH : $mess = "Type de données incorrect."; break;
    case SQLITE_MISUSE : $mess = "Bibliothèque utilisée incorrectement."; break;
    case SQLITE_NOLFS : $mess = "Utilisation de fonctionnalités de l'OS non supportées sur l'hôte."; break;
    case SQLITE_AUTH : $mess = "Autorisation échouée."; break;
    case SQLITE_ROW : $mess = "Processus interne a une autre ligne prête."; break;
    case SQLITE_DONE : $mess = "Processus interne a terminé l'exécution."; break;
    default  : $mess = "Erreur inconnue";
  }
  return $mess;
}

function purge_db() {
		$liste = array();
		foreach($_SESSION as $c => $s) {
			if (substr($c,0,6)=='copie_') {
				if (file_exists($s)) unlink($s);
			}
		}	
}

function get($requete,$base) {
	$fic=$_SESSION["copie_$base"];
	$db=new SQLite3($fic);
	$table = array();
	$requete = $db->escapeString($requete); //$db->escapeString(utf8_decode($requete));	
	//echo '<p>Get</p>';
	if ($db) {//Connexion réussie !
		//Soumission de la requête à la base
		$erreur='';//echo '<p>Connexion ok</p>';
		$result=$db->query($requete);
		if ($result) {
			//Exploitation des résultats
			    $ligne = $result->fetchArray(SQLITE3_ASSOC);
				$titre = array_keys($ligne); ?>
				<table cellspacing="5" border="2" cellpadding="2">
				<thead>
					<tr><?php
					for($i=0;$i<count($titre);$i=$i+1) {
						echo "<th>$titre[$i]</th>";
					}?></tr>
				</thead>
				<tbody>
				<?php $result->reset(); //sqlite_seek($result,0);
				 	$nbtuples = 0;
				  while($ligne = $result->fetchArray(SQLITE3_NUM)) {
					echo("<tr>");
					for($j=0;$j<count($ligne);$j=$j+1) {
					  $tu = $ligne[$j];//utf8_encode($ligne[$j]);
						echo "<td>$tu</td>";
					}
					echo("</tr>\n");
					$table[$nbtuples++] = $ligne;
				}?>
				</tbody>
				</table><?php
			echo("<script language='javascript'>result_type='ok';</script> <p> $nbtuples ligne(s) obtenue(s)</p>");
		} else {
			echo '<p>Problème dans la requête</p>';
			$no_err = $db->lastErrorCode();
			$erreur2 = $db->lastErrorMsg();
			echo("<script language='javascript'>result_type='ko';</script><p>$erreur2 ($no_err) </p>");
		} 
		//Fermeture de la connection à la base
		$db->close();
	} else {//Echec de la connection
		$erreur0 = $db->lastErrorMsg();
		echo("<script language='javascript'>result_type='ko';</script><p> Problème de connexion : $erreur0 </p> <div id='type-res' value='ko'/>");
	}
	return $table;
}

function get_tables($base) {
	$requete = 'SELECT * FROM sqlite_master WHERE type="table" order by lower(name)';
	$fic = $_SESSION["copie_$base"] ;
	//echo "<p>recup tables : $fic</p>";
	$db=new SQLite3($fic);
	$table = array();

	if ($db) {//Connection réussie !
		//echo "<p>connexion réussie</p>";
		//Soumission de la requête à la base
		$result = $db->query($requete);
		if ($result) {
			//echo "<p>requête réussie</p>";
			while($ligne = $result->fetchArray(SQLITE3_NUM)) {
				//var_dump($ligne);
				$table[] = $ligne[1];
				//echo "<p>$ligne[1]</p>";
			}
			//echo "<p>fin parcours requete</p>";
		} else {
			echo "<p>Problème dans la lecture de la structure de la base $base</p>";
			$erreur1 = $db->lastErrorCode();
			$erreur2 = $db->lastErrorMsg();
			echo("<p> $erreur1 <br/> $erreur2 </p>");
		} 
		//Fermeture de la connection à la base
		$db->close();
	} else {//Echec de la connection
		$erreur0 = $db->lastErrorMsg();
		echo("<p> Problème de connexion : $erreur0 </p>");
	}
	return $table;
}

function liste_base($base) {
	$tables = get_tables($base);	
	//echo "<p>Ds lb</p>";
	//var_dump($tables);
	foreach($tables as $ta) {
		echo "<a id='$ta'></a><div class='post'>";
		echo '	<h2 class="title">'."Table $ta".'</h2>';
		echo '    <div class="story">';
		$t = get("select * from ".$ta." order by 1",$base);
		echo "<p><a href='#matiere'><img src='images/up_64.png' alt='Sommaire' title='Sommaire' width='32'/></a></p>";
		echo '</div></div>';
	}
}

function verifFich($base){
	$config = new SimpleXMLElement('./config.xml',
        LIBXML_DTDATTR|LIBXML_DTDLOAD|LIBXML_DTDVALID
        |LIBXML_NOBLANKS|LIBXML_NOCDATA,
        true);
	$rep = $config->dossierBdD ;
	if($base == "tp-vols"){
		$fich = "aeroport";
	}
	else if($base == "tp-cinema"){
		$fich = "cinema";
	}
	else if($base == "cours"){
		$fich = "cours";
	}
	else if($base == "tp-em"){
		$fich = "em";
	}
	else if($base == "tp-Lutins"){
		$fich = "lutins";
	}
	else if($base == "tp-robots"){
		$fich = "robots";
	}
	if (is_dir($rep)) {
		if ($iter = opendir($rep)) {
			while (($fichier = readdir($iter)) !== false)  {  
				if($fichier != "." && $fichier != ".." && $fichier != "Thumbs.db")  {  
					if (is_dir($rep.'/'.$fichier)) {
						$conf = $rep.'/'.$fichier.'/config.xml';
						if (file_exists($conf)) {
							if($fichier == $fich){
								return $xml = simplexml_load_file($conf);
							}
						}
					}
				}
			}
		}
	}
}

function liste_TP($base){
	
	$tpDispo = false;
	$xml = verifFich($base);
	$listeTP = 'liste-TP';
	$objectif = 'objectif-pédagogique';
	$listeTp = ($xml->$listeTP);
	echo "<a id='matiere'></a>";
	echo "<div class='post'><h2 class='title'>Travaux pratiques</h2>";
	echo "<ul>";
	foreach ($listeTp->TP as $tp){
		$tpDispo = true;
		echo "<a href='#' onClick='questions(\"$base\", \"$tp->name\");return false;' style='cursor:pointer'>";
		echo "<li>";
		echo "<img src='images/down_64.png' height='16' width='16' /> ";
		echo $tp->name;
		echo "</a>";
		echo "</li>";
	}
	echo "</ul>";
	echo "</div>";
	if($tpDispo == false){
		echo "<h2 class='title'>Aucun TP disponible actuellement</h2>";
	}
}

function liste_question($base, $tp_name){

	$xml = verifFich($base);
	$listeQUEST = 'liste-questions';
	$listeQuestion = ($xml->$listeQUEST);

	$listeTP = 'liste-TP';
	$objectif = 'objectif-pédagogique';
	$listeTp = ($xml->$listeTP);
	$refQuest = 'ref-question';

	$questionDispo = false;

	echo "<a id='matiere'></a>";
	echo "<div class='post'><h2 class='title'>Questions du TP $tp_name</h2>";
	foreach($listeTp->TP as $tp){
		if($tp->name == $tp_name){
			foreach($tp->$refQuest as $refQuestion){
				foreach ($listeQuestion->children() as $Quest){
					if((string)$refQuestion->name_question == (string)$Quest->name_quest){
						$questionDispo = true;
						$i=$i+1;
						echo "Question ".$i;
						echo "<li>";
						echo "Intention :";
						echo $Quest->intention;
						echo "</li>";
						echo "<li>";
						echo "Aide :";
						echo $Quest->aide;
						echo "</li>";
						echo "<br>"."</br>";
					}
				}
			}
		}
	}
	echo "</div>";
	if($questionDispo == false){
		echo "<h2 class='title'>Aucun question n'est disponible actuellement</h2>";
	}		

}

?>