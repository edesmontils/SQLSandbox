<?php
// Questions d'un thème : questionsThem.php?base=<nom de la base>&theme=<thème>
require './session.php';
require_once('./lib/LibXML.php');
include "./fonctions.php";

debut_page();
if (session_initialisee()) {
	liste_question_thema(isset($_GET['base']) ? $_GET['base'] : null, isset($_GET['theme']) ? $_GET['theme'] : null);
}
fin_page();
