<?php

// Jeton anti-CSRF, généré par index.php et renvoyé par le JavaScript
function jeton_valide() {
	return isset($_SESSION['csrf'], $_REQUEST['csrf'])
		&& is_string($_REQUEST['csrf'])
		&& hash_equals($_SESSION['csrf'], $_REQUEST['csrf']);
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
	$requete = "SELECT name FROM sqlite_master WHERE type IN ('table','view') AND name NOT LIKE 'sqlite_%' ORDER BY lower(name)";
	$table = array();
	$db = ouvre_base($base);
	if (is_null($db)) {
		echo "<p>Base ".htmlspecialchars($base)." inconnue ou inaccessible</p>";
		return $table;
	}
	$result = $db->query($requete);
	if ($result) {
		while($ligne = $result->fetchArray(SQLITE3_NUM)) {
			$table[] = $ligne[0];
		}
	} else {
		echo "<p>Problème dans la lecture de la structure de la base ".htmlspecialchars($base)."</p>";
		echo "<p>".(int)$db->lastErrorCode()." <br/> ".htmlspecialchars($db->lastErrorMsg())."</p>";
	}
	$db->close();
	return $table;
}

function liste_base($base) {
	if (!is_string($base) || !isset($_SESSION["copie_$base"])) {
		echo "<p>Rubrique ou base inconnue.</p>";
		return;
	}
	$tables = get_tables($base);
	foreach($tables as $ta) {
		$nom = htmlspecialchars($ta, ENT_QUOTES);
		echo "<a id='$nom'></a><div class='post'>";
		echo '	<h2 class="title">'."Table $nom".'</h2>';
		echo '    <div class="story">';
		get('select * from "'.str_replace('"','""',$ta).'" order by 1',$base);
		echo "<p><a href='#matiere'><img src='images/up_64.png' alt='Sommaire' title='Sommaire' width='32'/></a></p>";
		echo '</div></div>';
	}
}
?>