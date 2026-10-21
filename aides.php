<?php
// Aide sur les icônes de l'application (contenu de config.xml)
require './session.php';
require_once('./lib/LibXML.php');
include "./fonctions.php";

debut_page();
try {
	$config = new SimpleXMLElement('./config.xml',
	                               LIBXML_DTDATTR|LIBXML_DTDLOAD|LIBXML_DTDVALID
	                               |LIBXML_NOBLANKS|LIBXML_NOCDATA,
	                               true);
	foreach($config->aides->children() as $c)
		echo $c->asXML();
} catch(Exception $e) {
	echo "<p>erreur<br/>Pb (Exception) ! ".html($e->getMessage())."<br/>".$libxml->afficheErreurs()."<br/></p>";
}
fin_page();
