<?php
// Nom de cette installation : il donne le nom du cookie de session et des dossiers de
// travail. Deux installations sur un même hébergement (par exemple une version de test
// à côté de celle en service) doivent avoir des noms différents, sinon elles partagent
// cookie et sessions, et l'ouverture de l'une réinitialise les bases de l'autre.
if (!defined('INSTANCE')) define('INSTANCE', 'sqlsandbox');

// Dossiers de travail de l'application (copies des bases, verrous, sessions).
// Ils sont placés hors du site, à côté de la racine web (par exemple ~/sqlsandbox-tmp
// chez OVH), pour ne pas dépendre d'un .htaccess : un fichier caché se perd facilement
// lors d'un envoi par FTP. À défaut, on se replie sur un dossier du site ($repli).
function dossier_prive($nom, $repli) {
	$candidats = array();
	if (!empty($_SERVER['DOCUMENT_ROOT']))
		$candidats[] = dirname(rtrim($_SERVER['DOCUMENT_ROOT'], '/')).'/'.$nom;
	$candidats[] = $repli;
	foreach ($candidats as $dossier) {
		if (!@is_dir($dossier)) @mkdir($dossier, 0700);
		if (@is_dir($dossier) && @is_writable($dossier)) return realpath($dossier);
	}
	return null;
}

// Copies des bases de chaque session et verrous d'exécution (null si aucun dossier
// n'est accessible en écriture)
if (!defined('DOSSIER_TMP')) define('DOSSIER_TMP', dossier_prive(INSTANCE.'-tmp', __DIR__.'/tmp'));

// Fichier sqlsandbox.ini de cette installation, hors du site : variable d'environnement
// SQLSANDBOX_INI, puis à côté de la racine web (~/sqlsandbox.ini chez OVH) ; null s'il
// n'existe pas. Voir exos_ini() (exercices.php) et duree_vie().
function fichier_ini() {
	$candidats = array();
	if (getenv('SQLSANDBOX_INI')) $candidats[] = getenv('SQLSANDBOX_INI');
	if (!empty($_SERVER['DOCUMENT_ROOT']))
		$candidats[] = dirname(rtrim($_SERVER['DOCUMENT_ROOT'], '/')).'/'.INSTANCE.'.ini';
	foreach ($candidats as $f) if (is_file($f)) return $f;
	return null;
}

// Durée de vie (secondes) d'une session et des copies de bases d'un utilisateur, depuis
// l'inactivité : duree_session_min (minutes) de sqlsandbox.ini s'il est renseigné, sinon le
// « seuil » de config.xml (3 heures par défaut). Lue sans analyser tout le XML, car chaque
// requête en a besoin avant de démarrer la session. C'est l'unique source de cette durée :
// cookie, session, copies de bases et purge s'en servent, et restent donc alignés.
// $ini : fichier à lire (fichier_ini() par défaut, '' pour l'ignorer).
function duree_vie($ini = null) {
	if (is_null($ini)) $ini = fichier_ini() ?? '';
	if ($ini !== '' && is_file($ini) && is_readable($ini)) {
		$valeurs = @parse_ini_file($ini);
		if (is_array($valeurs) && isset($valeurs['duree_session_min']) && (int)$valeurs['duree_session_min'] > 0)
			return max(60, (int)$valeurs['duree_session_min'] * 60);
	}
	$xml = @file_get_contents(__DIR__.'/config.xml');
	if ($xml !== false) {
		if (preg_match("/<!ENTITY\\s+seuil\\s+['\"]\\s*(\\d+)\\s*['\"]/", $xml, $m) && preg_match('#<seuil>\\s*&seuil;\\s*</seuil>#', $xml)) return max(60, (int)$m[1]);
		if (preg_match('#<seuil>\\s*(\\d+)\\s*</seuil>#', $xml, $m)) return max(60, (int)$m[1]);
	}
	return 3 * 60 * 60;
}

// Durée en clair pour les messages (y compris celui de session perdue de
// controler.php, qui ne charge pas fonctions.php) : « 3 heures », « 1 heure », « 1 h 30 », « 45 minutes »
function duree_lisible($secondes) {
	$minutes = (int)round($secondes / 60);
	if ($minutes < 60) return $minutes.' minute'.($minutes > 1 ? 's' : '');
	$h = intdiv($minutes, 60);
	$m = $minutes % 60;
	return $m == 0 ? $h.' heure'.($h > 1 ? 's' : '') : $h.' h '.sprintf('%02d', $m);
}

