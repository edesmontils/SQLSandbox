<?php 
//============================================================================================//
//== LibXML.php :                                                                           ==//
//==                                                                                        ==//
//== (c) E. Desmontils, Université de Nantes, juillet 2009                                  ==//
//== Ce code est distribué sous la licence GNU GPL version 3 (voir le fichier LICENSE).     ==//
//== https://www.gnu.org/licenses/gpl-3.0.html                                              ==//
//============================================================================================//

class LibXML {
private $debug;

	function __construct($deb=false) {
		libxml_use_internal_errors(true);
		$this->debug = $deb;
	}
	
	protected function display_xml_error($error){
	   $return = "";
	   switch ($error->level) {
	       case LIBXML_ERR_WARNING: $return .= "Warning $error->code: ";break;
	       case LIBXML_ERR_ERROR: $return .= "Error $error->code: ";break;
	       case LIBXML_ERR_FATAL: $return .= "Fatal Error $error->code: ";break;
	       default : $return .= "Unknown Error $error->code: ";
	   }
	
	   $return .= trim($error->message) .
	               "\n<br/>  Line: $error->line" .
	               "\n<br/>  Column: $error->column";
	   if ($error->file) {$return .= "\n<br/>  File: $error->file";}
	   return "$return\n<br/>";
	}
	
	function afficheErreurs() {
		$tab_erreurs = libxml_get_errors();
		$rep = "";
		if ($this->debug) $rep .= "Affichage des erreurs :\n<br/>";
	   	foreach ($tab_erreurs as $error) {
	   		$detail = $this->display_xml_error($error);
	   		// Hors débogage, le détail (avec les chemins des fichiers) ne va qu'au journal
	   		if ($this->debug) $rep .= $detail;
	   		else error_log('LibXML : '.strip_tags(str_replace("\n", ' ', $detail)));
	   	}
	   	if ($this->debug) $rep .= "Fin affichage des erreurs :\n<br/>";
	   	else if (count($tab_erreurs) > 0) $rep = "Fichier XML invalide (détails dans le journal du serveur).";
		libxml_clear_errors();
		return $rep;
	}
	
	function afficheDerniereErreur() {
		$error = libxml_get_last_error();
		return ($error && $this->debug) ? $this->display_xml_error($error) : '';
	}
}

$libxml = new LibXML();
?>