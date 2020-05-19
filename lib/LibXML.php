<?php 
//============================================================================================//
//== LibXML.php :                                                                           ==//
//==                                                                                        ==//
//== (c) E. Desmontils, Université de Nantes, juillet 2009                                  ==//
//== Ce code est mis à disposition selon les termes de la licence Creative Commons          ==//
//== Paternité-Pas d'Utilisation Commerciale-Pas de Modification 2.0 France.                ==//
//== http://creativecommons.org/licenses/by-nc-nd/2.0/fr/                                   ==//
//============================================================================================//

class LibXML {
private $debug;

	function __construct($deb=true) {
		libxml_use_internal_errors(true);
		$this->debug = $deb;
	}
	
	protected function display_xml_error($error){	   $return = "";	   switch ($error->level) {	       case LIBXML_ERR_WARNING: $return .= "Warning $error->code: ";break;	       case LIBXML_ERR_ERROR: $return .= "Error $error->code: ";break;	       case LIBXML_ERR_FATAL: $return .= "Fatal Error $error->code: ";break;
	       default : $return .= "Unknown Error $error->code: ";	   }		   $return .= trim($error->message) .	               "\n<br/>  Line: $error->line" .	               "\n<br/>  Column: $error->column";	   if ($error->file) {$return .= "\n<br/>  File: $error->file";}	   return "$return\n<br/>";	}
	
	function afficheErreurs() {
		$tab_erreurs = libxml_get_errors();
		$rep = "";
		if ($this->debug) $rep .= "Affichage des erreurs :\n<br/>";	   	foreach ($tab_erreurs as $error) {	   		if ($this->debug) $rep .= $this->display_xml_error($error);
	   	}
	   	if ($this->debug) $rep .= "Fin affichage des erreurs :\n<br/>";
		libxml_clear_errors();
		return $rep;
	}
	
	function afficheDerniereErreur() {
		$error = libxml_get_last_error();
		$rep = display_xml_error($error);
		return $rep;
	}
}

$libxml = new LibXML();
?>