<?php
// Démarrage de session commun à toutes les pages (cookie protégé)
ini_set('session.cookie_httponly','1');
ini_set('session.cookie_samesite','Lax');
ini_set('session.use_strict_mode','1');
// Pas d'erreurs PHP brutes (chemins, messages internes) dans les pages ;
// le mode debug de config.xml les réactive.
ini_set('display_errors', '0');
// Cookie réservé à HTTPS quand le site est servi en HTTPS
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
$session_ok = session_start();
// L'application ne doit pas être affichée dans un cadre d'un autre site (clickjacking)
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
setlocale(LC_TIME,'fr_FR');
