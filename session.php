<?php
// Démarrage de session commun à toutes les pages (cookie protégé)
ini_set('session.cookie_httponly','1');
ini_set('session.cookie_samesite','Lax');
ini_set('session.use_strict_mode','1');
$session_ok = session_start();
setlocale(LC_TIME,'fr_FR');
