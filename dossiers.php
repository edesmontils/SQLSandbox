<?php
// Nom de cette installation : il donne le nom du cookie de session et des dossiers de
// travail. Deux installations sur un même hébergement (la 5.0 en service et la 6.0 en
// test) doivent avoir des noms différents, sinon elles partagent cookie et sessions,
// et l'ouverture de l'une réinitialise les bases de l'autre.
if (!defined('INSTANCE')) define('INSTANCE', 'sqlsandbox6');

// Dossiers de travail de l'application (copies des bases, verrous, sessions).
// Ils sont placés hors du site, à côté de la racine web (par exemple ~/sqlsandbox6-tmp
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
