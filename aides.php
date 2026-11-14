<?php
// Aide sur les icônes de l'application (contenu de config.xml)
require './session.php';
require_once('./lib/LibXML.php');
include "./fonctions.php";

debut_page();
try {
	foreach(charge_config()->aides->children() as $c)
		echo $c->asXML();
} catch(Exception $e) {
	echo erreur_config($e);
}
fin_page();
