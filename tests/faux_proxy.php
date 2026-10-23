<?php
// Faux ProxyIAg pour tests/run.php : renvoie le contenu du fichier FAUX_REPONSE (code HTTP,
// saut de ligne, corps) et garde la requête reçue dans FAUX_REPONSE.requete.
$fichier = getenv('FAUX_REPONSE');
list($code, $corps) = explode("\n", (string)@file_get_contents($fichier), 2) + array('500', '');
file_put_contents($fichier.'.requete', ($_SERVER['HTTP_AUTHORIZATION'] ?? '')."\n".file_get_contents('php://input'));
http_response_code((int)$code);
header('Content-Type: application/json');
echo $corps;
