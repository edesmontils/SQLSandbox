<?php
// Routeur du serveur intégré de PHP pour tests/run.php : reproduit la règle du serveur web
// (.htaccess sous Apache, Caddyfile sur le Raspberry Pi), qui sert /123456 (tenant 123456 de
// QueryCraft) par index.php?qc=123456 et renvoie /123456/ vers /123456. Le reste est servi
// normalement.
$chemin = (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/([0-9]{6})/$#', $chemin, $m)) {
	header('Location: /'.$m[1], true, 308);
	exit;
}
if (preg_match('#^/([0-9]{6})$#', $chemin, $m)) {
	$_GET['qc'] = $m[1];
	chdir(dirname(__DIR__));
	require dirname(__DIR__).'/index.php';
	exit;
}
return false;
