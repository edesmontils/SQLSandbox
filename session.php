<?php
// Démarrage de session commun à toutes les pages (cookie protégé)
ini_set('session.cookie_httponly','1');
ini_set('session.cookie_samesite','Lax');
ini_set('session.use_strict_mode','1');
// Pas d'erreurs PHP brutes (chemins, messages internes) dans les pages ;
// le mode debug de config.xml les réactive.
ini_set('display_errors', '0');
$session_ok = session_start();
setlocale(LC_TIME,'fr_FR');
