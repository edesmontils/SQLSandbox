<?php
// Ancienne page des questions d'un TP (tp.php?base=<base>&tp=<TP>) : les TP s'ouvrent
// maintenant dans la page principale, qui reprend le TP demandé au chargement
$base = $_GET['base'] ?? '';
$tp = $_GET['tp'] ?? '';
$url = 'index.php';
if (is_string($base) && is_string($tp) && $base !== '' && $tp !== '')
	$url .= '?'.http_build_query(array('base' => $base, 'tp' => $tp));
header('Location: '.$url, true, 302);
