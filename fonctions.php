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
// Taille maximale (octets) d'une copie de base, pour éviter de remplir le disque
define('TAILLE_MAX_BASE', 20 * 1024 * 1024);

// PRAGMA en lecture seule utiles pour consulter la structure des tables
$GLOBALS['PRAGMAS_AUTORISES'] = array('table_info', 'table_xinfo', 'index_list',
                                      'index_info', 'index_xinfo', 'foreign_key_list');

// Autorise ou refuse les actions SQL. ATTACH/DETACH (et donc VACUUM INTO)
// permettraient d'écrire des fichiers arbitraires sur le serveur, les autres PRAGMA
// de modifier le comportement de la base (dont la limite de taille) et
// fts3_tokenizer() divulgue des adresses mémoire.
function autorisation_sql($action, $arg1 = null, $arg2 = null) {
	switch ($action) {
		case SQLite3::ATTACH:
		case SQLite3::DETACH:
			return SQLite3::DENY;
		case SQLite3::PRAGMA:
			return in_array(strtolower((string)$arg1), $GLOBALS['PRAGMAS_AUTORISES'], true)
				? SQLite3::OK : SQLite3::DENY;
		case SQLite3::FUNCTION:
			return strtolower((string)$arg2) == 'fts3_tokenizer' ? SQLite3::DENY : SQLite3::OK;
		default:
			return SQLite3::OK;
	}
}

// Indique si $sql contient autre chose qu'une seule instruction (SQLite n'exécute
// que la première). Tient compte des chaînes, identifiants, commentaires et
// des corps de triggers (BEGIN ... END), qui contiennent des ';'.
function plusieurs_instructions($sql) {
	$n = strlen($sql);
	$i = 0;
	$mots = array();   // premiers mots de l'instruction
	$trigger = false;  // dans le corps BEGIN ... END d'un CREATE TRIGGER
	$cas = 0;          // profondeur des CASE ... END dans un trigger
	$fin = false;      // un ';' terminant la première instruction a été vu
	while ($i < $n) {
		$c = $sql[$i];
		if ($c == "'" || $c == '"' || $c == '`' || $c == '[') {
			if ($fin) return true;
			$ferme = ($c == '[') ? ']' : $c;
			$j = strpos($sql, $ferme, $i + 1);
			$i = ($j === false) ? $n : $j + 1;
		} else if ($c == '-' && $i + 1 < $n && $sql[$i + 1] == '-') {
			$j = strpos($sql, "\n", $i);
			$i = ($j === false) ? $n : $j + 1;
		} else if ($c == '/' && $i + 1 < $n && $sql[$i + 1] == '*') {
			$j = strpos($sql, '*/', $i + 2);
			$i = ($j === false) ? $n : $j + 2;
		} else if (ctype_alpha($c) || $c == '_') {
			if ($fin) return true;
			$j = $i;
			while ($j < $n && (ctype_alnum($sql[$j]) || $sql[$j] == '_')) $j++;
			$mot = strtoupper(substr($sql, $i, $j - $i));
			if (count($mots) < 4) $mots[] = $mot;
			if ($mot == 'BEGIN' && $mots[0] == 'CREATE' && in_array('TRIGGER', $mots)) $trigger = true;
			else if ($trigger && $mot == 'CASE') $cas++;
			else if ($trigger && $mot == 'END') {
				if ($cas > 0) $cas--; else $trigger = false;
			}
			$i = $j;
		} else if ($c == ';') {
			if (!$trigger) $fin = true;
			$i++;
		} else {
			if ($fin && !ctype_space($c)) return true;
			$i++;
		}
	}
	return false;
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
	// Limite de taille, fixée avant l'autorizer qui interdit ensuite de la modifier
	$taille_page = (int)$db->querySingle('PRAGMA page_size');
	if ($taille_page > 0) $db->exec('PRAGMA max_page_count = '.(int)(TAILLE_MAX_BASE / $taille_page));
	$db->setAuthorizer('autorisation_sql');
	return $db;
}

// Message d'erreur SQLite échappé, complété si la base a atteint sa taille maximale
function message_erreur($db) {
	$m = htmlspecialchars($db->lastErrorMsg()).' ('.$db->lastErrorCode().')';
	if ($db->lastErrorCode() == 13) // SQLITE_FULL
		$m .= ' : la base a atteint sa taille maximale ('.(TAILLE_MAX_BASE / 1024 / 1024).' Mo).';
	return $m;
}

// Affiche un message lisible si la requête est arrêtée par la limite de durée
function arret_requete() {
	$e = error_get_last();
	if ($e && $e['type'] == E_ERROR && strpos($e['message'], 'Maximum execution time') !== false) {
		echo "<script language='javascript'>result_type='ko';</script>"
		    ."<p>Requête interrompue : durée maximale d'exécution dépassée (".DELAI_MAX." s).</p>";
	}
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
	static $arret_enregistre = false;
	if (!$arret_enregistre) {
		register_shutdown_function('arret_requete');
		$arret_enregistre = true;
	}
	if (plusieurs_instructions($requete)) {
		echo "<p><strong>Attention :</strong> seule la première instruction a été exécutée, la suite a été ignorée.</p>";
	}
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
			while ($ligne = @$result->fetchArray(SQLITE3_NUM)) {
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
				echo '<script language="javascript">result_type=\'ko\';</script><p>'.message_erreur($db).'</p>';
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
		echo "<script language='javascript'>result_type='ko';</script><p>".message_erreur($db)."</p>";
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