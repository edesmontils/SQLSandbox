<?php
// Questions d'un thème : questionsThem.php?base=<nom de la base>&theme=<thème>
require './session.php';
require_once('./lib/LibXML.php');
include "./fonctions.php";

debut_page();
if (session_initialisee()) {
	liste_question_thema(param('base'), param('theme'));
}
fin_page();
