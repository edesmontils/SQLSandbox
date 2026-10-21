<?php
// Liste des questions d'un TP : tp.php?base=<nom de la base>&tp=<nom du TP>
require './session.php';
require_once('./lib/LibXML.php');
include "./fonctions.php";

debut_page();
if (session_initialisee()) {
	liste_question(param('base'), param('tp'));
}
fin_page();
