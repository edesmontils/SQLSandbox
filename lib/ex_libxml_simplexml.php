<?php 

//============================================================================================//
//== getElementsByAttribute.php : solution proposée pour l'exercice 01, section Autres API, ==//
//== chapitre "Programmer XML" - cours "XML : concepts de base"                             ==//
//==                                                                                        ==//
//== (c) E. Desmontils, Université de Nantes, juillet 2009                                  ==//
//== Ce code est mis à disposition selon les termes de la licence Creative Commons          ==//
//== Paternité-Pas d'Utilisation Commerciale-Pas de Modification 2.0 France.                ==//
//== http://creativecommons.org/licenses/by-nc-nd/2.0/fr/                                   ==//
//============================================================================================//

header('Content-type: text/plain; charset=utf-8');
include_once("LibXML.php");

function getElementsByAttribute($doc, $attribute, $attributeValue=null) {
		if (isset($attributeValue)) 
			return $doc->xpath("//node()[@$attribute = '$attributeValue']");
		else return $doc->xpath("//node()[@$attribute]");
}

try {
    $doc = new SimpleXMLElement('../xml/edt1213.xml',
                                LIBXML_DTDATTR|LIBXML_DTDLOAD|LIBXML_DTDVALID,
                                TRUE);
    if ($doc) {
        $l = getElementsByAttribute($doc,'nplage','c2');
        foreach($l as $node) echo $node->asXML()."\n";
     } else {
        echo "\nPb de chargement\n";
        $libxml->afficheErreurs();
     }
} catch(Exception $e ) {
  echo "Pb (Exception) ! $e\n";
  $libxml->afficheErreurs();
  die($e);
}?>