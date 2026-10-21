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

// Nombre maximal de lignes affichées pour un résultat
define('MAX_LIGNES', 1000);
// Durée maximale (secondes) d'attente d'un verrou et d'exécution PHP
define('DELAI_MAX', 10);

// Autorise ou refuse les actions SQL. ATTACH/DETACH (et donc VACUUM INTO)
// permettraient d'écrire des fichiers arbitraires sur le serveur, PRAGMA de
// modifier le comportement de la base.
function autorisation_sql($action) {
	switch ($action) {
		case SQLite3::ATTACH:
		case SQLite3::DETACH:
		case SQLite3::PRAGMA:
			return SQLite3::DENY;
		default:
			return SQLite3::OK;
	}
}

// Ouvre la copie de session de la base $base (null si elle n'existe pas).
function ouvre_base($base) {
	if (!is_string($base) || !isset($_SESSION["copie_$base"])) return null;
	$fic = $_SESSION["copie_$base"];
	if (!is_file($fic)) return null;
	try {
		$db = new SQLite3($fic, SQLITE3_OPEN_READWRITE);
	} catch (Exception $e) {
		return null;
	}
	$db->busyTimeout(DELAI_MAX * 1000);
	$db->enableExceptions(false);
	$db->setAuthorizer('autorisation_sql');
	return $db;
}

// Exécute $requete sur la base $base et affiche le résultat.
// Retourne true en cas de succès, null sinon.
function get($requete,$base) {
	$db = ouvre_base($base);
	if (is_null($db)) {
		echo "<script language='javascript'>result_type='ko';</script><p>Base inconnue ou inaccessible.</p>";
		return null;
	}
	set_time_limit(DELAI_MAX);
	$ok = null;
	$result = @$db->query($requete);
	if ($result) {
		$nbcol = $result->numColumns();
		if ($nbcol == 0) {
			// Requête sans résultat tabulaire (INSERT, UPDATE, CREATE...) : déjà exécutée
			$nb = $db->changes();
			echo "<script language='javascript'>result_type='ok';</script><p>Requête exécutée ($nb ligne(s) modifiée(s))</p>";
		} else {
			echo '<table cellspacing="5" border="2" cellpadding="2"><thead><tr>';
			for ($i = 0; $i < $nbcol; $i++) {
				echo '<th>'.htmlspecialchars($result->columnName($i)).'</th>';
			}
			echo "</tr></thead>\n<tbody>\n";
			$nbtuples = 0;
			$tronque = false;
			while ($ligne = $result->fetchArray(SQLITE3_NUM)) {
				if ($nbtuples >= MAX_LIGNES) { $tronque = true; break; }
				echo '<tr>';
				foreach ($ligne as $valeur) {
					echo '<td>'.htmlspecialchars((string)$valeur).'</td>';
				}
				echo "</tr>\n";
				$nbtuples++;
			}
			echo '</tbody></table>';
			if ($db->lastErrorCode() != 0 && $db->lastErrorCode() != 100 && $db->lastErrorCode() != 101) {
				echo '<script language="javascript">result_type=\'ko\';</script><p>'.htmlspecialchars($db->lastErrorMsg()).' ('.$db->lastErrorCode().')</p>';
			} else {
				echo "<script language='javascript'>result_type='ok';</script><p> $nbtuples ligne(s) obtenue(s)"
				     .($tronque ? ' (affichage limité aux '.MAX_LIGNES.' premières lignes)' : '').'</p>';
				$ok = true;
			}
			$result->finalize();
		}
		if (is_null($ok) && $nbcol == 0) $ok = true;
	} else {
		echo '<p>Problème dans la requête</p>';
		$erreur = htmlspecialchars($db->lastErrorMsg());
		echo "<script language='javascript'>result_type='ko';</script><p>$erreur (".$db->lastErrorCode().") </p>";
	}
	$db->close();
	return $ok;
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
?>