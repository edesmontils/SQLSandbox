<?php
// Liste des questions d'un TP : tp.php?base=<nom de la base>&tp=<nom du TP>
require './session.php';
require_once('./lib/LibXML.php');
include "./fonctions.php";

debut_page();
if (session_initialisee()) {
	liste_question(isset($_GET['base']) ? $_GET['base'] : null, isset($_GET['tp']) ? $_GET['tp'] : null);
}
fin_page();
