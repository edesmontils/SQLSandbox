<?php
// Démarrage de session commun à toutes les pages (cookie protégé)
ini_set('session.cookie_httponly','1');
ini_set('session.cookie_samesite','Lax');
ini_set('session.use_strict_mode','1');
// Pas d'erreurs PHP brutes (chemins, messages internes) dans les pages ;
// le mode debug de config.xml les réactive.
ini_set('display_errors', '0');
// Durée de vie des sessions alignée sur celle des copies de bases (seuil de config.xml) :
// la valeur par défaut de PHP (24 min d'inactivité) coupait les élèves en plein TP.
// Les sessions ont leur propre dossier, pour que le nettoyage des autres sites de
// l'hébergement ne les supprime pas plus tôt ; PHP les nettoie lui-même, le nettoyage
// du système ne passant pas dans ce dossier.
// Le nom d'un fichier de session contient l'identifiant de session : le dossier est
// placé hors du site (dossiers.php), et à défaut dans tmp/sessions (avec un index.html
// vide, et protégé par tmp/.htaccess quand celui-ci est pris en compte).
define('DUREE_SESSION', 3 * 60 * 60);
require_once __DIR__.'/dossiers.php';
$dossierSessions = dossier_prive('sessions-sqlsandbox', __DIR__.'/tmp/sessions');
if (!is_null($dossierSessions)) {
	if (strpos($dossierSessions, __DIR__) === 0 && !is_file($dossierSessions.'/index.html'))
		@touch($dossierSessions.'/index.html'); // pas de liste des fichiers sans .htaccess
	ini_set('session.save_path', $dossierSessions);
	ini_set('session.gc_probability', '1');
	ini_set('session.gc_divisor', '100');
}
ini_set('session.gc_maxlifetime', (string)DUREE_SESSION);
// Cookie réservé à HTTPS quand le site est servi en HTTPS
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');
$session_ok = session_start();
// L'application ne doit pas être affichée dans un cadre d'un autre site (clickjacking)
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
// Permet au JavaScript de distinguer les réponses de l'application de celles d'un
// pare-feu ou d'un proxy de l'hébergeur
header('X-SQLSandbox: 1');
setlocale(LC_TIME,'fr_FR');
