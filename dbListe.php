<?php
// Contenu des tables d'une base : dbListe.php?base=<nom de la base>
require './session.php';
require_once('./lib/LibXML.php');
include "./fonctions.php";

debut_page();
if (session_initialisee()) {
	liste_base(param('base'));
}
fin_page();
