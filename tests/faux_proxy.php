<?php
// Faux ProxyIAg pour tests/run.php : renvoie le contenu du fichier FAUX_REPONSE (code HTTP,
// saut de ligne, corps) et garde la requête reçue dans FAUX_REPONSE.requete.
$fichier = getenv('FAUX_REPONSE');
// Faux QueryCraft : /…/catalogue/bases/<fichier> sert FAUX_REPONSE.bases/<fichier>
if (preg_match('#/catalogue/bases/([A-Za-z0-9_.-]+)$#', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), $m)) {
	$base = $fichier.'.bases/'.$m[1];
	if (!is_file($base)) { http_response_code(404); exit; }
	header('Content-Type: application/vnd.sqlite3');
	readfile($base);
	exit;
}
list($code, $corps) = explode("\n", (string)@file_get_contents($fichier), 2) + array('500', '');
file_put_contents($fichier.'.requete', ($_SERVER['HTTP_AUTHORIZATION'] ?? '')."\n".file_get_contents('php://input'));
file_put_contents($fichier.'.etag', $_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
file_put_contents($fichier.'.uri', $_SERVER['REQUEST_URI']);
http_response_code((int)$code);
header('Content-Type: application/json');
echo $corps;
