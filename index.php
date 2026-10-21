<?php 
require './session.php';
 //phpinfo();
$debug=false;
$ok = true;
$nom_appli = 'SQL Sandbox';
$version = '';
$seuil = 10800;
$listeBase = array();
$listeNoms = array();

//Récupération des paramètres
require_once('./lib/LibXML.php');
include "./fonctions.php";

// En cas de rechargement de la page, les bases restent... donc on les supprime !
if (isset($_SESSION['debug'])) {
	purge_db();	
	session_unset();
}
session_regenerate_id(true);
$_SESSION['csrf'] = bin2hex(random_bytes(16));

try { 
	$config = new SimpleXMLElement('./config.xml',
                                   LIBXML_DTDATTR|LIBXML_DTDLOAD|LIBXML_DTDVALID
                                   |LIBXML_NOBLANKS|LIBXML_NOCDATA,
                                   true);
	$att = $config->attributes();
	
	//Gestion de la version
	$version = (string)$att['version'];
	$nom_appli = (string)$att['name'];
	
	//mode "debug"
	$debug = ((string)$att['debug']) == 'true';
	$_SESSION['debug'] = $debug;
	if ($debug) ini_set('display_errors', '1');
	
	//Gestion du seuil de vie des copies de base //60 * 60 * 24;
	$seuil = (int)$config->seuil;
	
	//Gestion des bases
	$listeBase = array();

	//Définitions dans le fichier de config principal
	if (isset($config->listeBases)) foreach($config->listeBases->base_de_donnee as $base) { 
		$listeBase[(string)$base['nom']] = array(
			'config' => './config.xml',
			'fichier' => (string)$base->fichier['nom'],
			'prefixe' => (string)$base->fichier['prefixe'],
			'référence' => (string)$base->référence,
			'description' => (string)$base->description->asXML(),
			'tables' => array() //$tables
		);
		if ($debug) echo "<p>Description de ".$listeBase[(string)$base['nom']]['fichier']."</p>";
	}

	// Définitions dans un fichier de config spécialisé/dédié
	if (isset($config->listeBases)) foreach($config->listeBases->configBD as $configBD) {
		$BD = new SimpleXMLElement($configBD['localisation'].'/'.$configBD['fichier'],
                                   LIBXML_DTDATTR|LIBXML_DTDLOAD|LIBXML_DTDVALID
                                   |LIBXML_NOBLANKS|LIBXML_NOCDATA,
                                   true);
		$listeBase[(string)$BD['nom']] = array(
			'config' => $configBD['localisation'].'/'.$configBD['fichier'],
			'fichier' => $configBD['localisation'].'/'.(string)$BD->fichier['nom'],
			'prefixe' => (string)$BD->fichier['prefixe'],
			'référence' => (string)$BD->référence,
			'description' => (string)$BD->description->asXML(),
			'tables' => array() //$tables
		);
		if ($debug) echo "<p>Description de ".$listeBase[(string)$BD['nom']]['fichier']."</p>";
	}

	// Scan du répertoire BDD, on récupère uniquement ceux qui ont un fichier config.xml
	$rep = $config->dossierBdD ; // "./bdd" ;
	if (is_dir($rep)) {
		if ($iter = opendir($rep)) {
			while (($fichier = readdir($iter)) !== false)  {  
				if($fichier != "." && $fichier != ".." && $fichier != "Thumbs.db")  {  
					if (is_dir($rep.'/'.$fichier)) {
						$conf = $rep.'/'.$fichier.'/config.xml';
						if (file_exists($conf)) {						
							//echo '<a href="'.$rep.$fichier.'" target="_blank" >'.$fichier.'</a><br />'."\n";
							$BD = new SimpleXMLElement($conf,
					                                   LIBXML_DTDATTR|LIBXML_DTDLOAD|LIBXML_DTDVALID
					                                   |LIBXML_NOBLANKS|LIBXML_NOCDATA,
					                                   true);
							$listeBase[(string)$BD['nom']] = array(
								'config' => $conf,
								'fichier' => $rep.'/'.$fichier.'/'.(string)$BD->fichier['nom'],
								'prefixe' => (string)$BD->fichier['prefixe'],
								'référence' => (string)$BD->référence,
								'description' => (string)$BD->description->asXML(),
								'tables' => array() //$tables
							);
							if ($debug) echo "<p>Description de ".$listeBase[(string)$BD['nom']]['fichier']."</p>";
						}
					}  
				}  
			} 
			closedir($iter);  
		}
	}
	ksort($listeBase,SORT_NATURAL | SORT_FLAG_CASE);
	$listeNoms = array_keys($listeBase);
} catch(Exception $e) {
  echo "<p>erreur<br/>Pb (Exception) ! ".htmlspecialchars($e->getMessage())."<br/>".$libxml->afficheErreurs()
       ."<br/></p>";
}

if ($debug) {
	echo '<p>Mode Debug On</p>';
	if ($session_ok) 
		echo '<p>Session lancée correctement</p>';
		else echo '<p>Session incorrecte</p>';
	echo '<p>Nouvelle session</p>';
	echo "<p>Suppression des fichiers âgés de ".$seuil." secondes</p>";
}

// Purge de TMP s'il reste des fichiers non supprimés...
// (dans le dossier de travail, et dans tmp/ où se trouvaient les copies des versions précédentes)
foreach (array_unique(array_filter(array(DOSSIER_TMP, realpath('./tmp')))) as $dossier) {
	$dir=@opendir($dossier);
	while ($dir && ($nom=readdir($dir)) !== false) {
		$chemin = $dossier.'/'.$nom;
		if ((substr($nom,0,6)=='copie_' || substr($nom,0,7)=='verrou_') && is_file($chemin)) {
			$delta = time() - filemtime($chemin);
			if ($delta>$seuil) {
				if ($debug) echo "<p>Suppression de $nom (ancien de $delta secondes)</p>";
				unlink($chemin);
			}
		}
	}
	if ($dir) closedir($dir);
}

// Mise en place des BD temporaires pour l'utilisation courante (session)
if ($debug) echo "<p>Création des bases temporaires pour cette session</p>";
$dossierTmp = DOSSIER_TMP;
$erreursCopie = array();
if (is_null($dossierTmp)) {
	$erreursCopie[] = "Aucun dossier de travail accessible en écriture (".INSTANCE."-tmp à côté du site, ou tmp) : aucune base n'est disponible.";
	$listeBase = array();
	$listeNoms = array();
}
foreach($listeNoms as $nom) {
	// Seul un nom de fichier (vide) est réservé : la copie est faite à la première
	// requête sur la base (ouvre_base), pour qu'une simple visite ne coûte rien.
	// tempnam() se replie silencieusement sur le répertoire temporaire du système :
	// une copie créée ailleurs que dans le dossier de travail ne serait jamais purgée.
	$original = realpath($listeBase[$nom]['fichier']);
	$copie = @tempnam($dossierTmp, "copie_$nom".'_');
	if ($copie === false || dirname($copie) !== $dossierTmp
	    || $original === false || !is_readable($original)) {
		if ($copie !== false && is_file($copie)) unlink($copie);
		$erreursCopie[] = "Impossible de préparer la base ".htmlspecialchars($nom).".";
		unset($listeBase[$nom]);
		continue;
	}
	$_SESSION["copie_$nom"] = $copie;
	// Fichier de configuration (TP, questions), relu côté serveur sans passer par le navigateur
	$_SESSION['configs'][$nom] = $listeBase[$nom]['config'];
	// Base d'origine, pour recréer la copie si elle est purgée pendant un TP
	$_SESSION['originaux'][$nom] = $original;
	if ($debug) echo "<p>Réservation de ".$_SESSION["copie_$nom"]."</p>";

	$tables = get_tables($nom, $original);
	$listeBase[$nom]['tables'] = $tables;
}
$listeNoms = array_keys($listeBase);
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<!--
Design by Free CSS Templates
http://www.freecsstemplates.org
Released for free under a Creative Commons Attribution 2.5 License
-->
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8" />
  <title>SQL Sandbox</title>
  <meta name="keywords" content="" />
  <meta name="description" content="" />
  
  <meta property="og:title" content="SQLSandbox" />
<meta property="og:type" content="website" />
<meta property="og:url" content="http://www.desmontils.net/SQLSandbox/" />
<meta property="og:image" content="" />
<meta property="og:site_name" content="SQL Sandbox" />
<meta property="fb:admins" content="706563623" />
  
  <link href="default.css" rel="stylesheet" type="text/css" />
  
  <!-- Attention, ordre important ! -->
  <script src="./js/prototype.js" type="text/javascript"></script>
  <script src="./js/scriptaculous/scriptaculous.js" type="text/javascript"></script>
  <script type="text/javascript">  
    listeBases = <?php
    // On n'envoie au navigateur ni les chemins des fichiers ni les copies de session
    $pourClient = array();
    foreach($listeBase as $n => $b) {
    	$pourClient[$n] = array(
    		'référence' => $b['référence'],
    		'description' => $b['description'],
    		'tables' => $b['tables']
    	);
    }
    echo json_encode($pourClient, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE); ?>;
    noms_bases = <?php echo json_encode($listeNoms, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE); ?>;
    csrf_token = <?php echo json_encode($_SESSION['csrf']); ?>;
  </script>
  <script src="./js/bd_base.js" type="text/javascript"></script>
 
</head>

<body onload="init()" onunload="end()">

<div id="header">
	<h1><?php echo htmlspecialchars($nom_appli);?></h1>
	<p>Version <?php echo htmlspecialchars($version);?></p>
</div>

<div id="content">

	<?php if (count($erreursCopie) > 0) {
		echo "<div class='post'><h2 class='title'>Problème d'installation</h2><div class='story'>";
		foreach($erreursCopie as $m) echo "<p>$m</p>";
		echo "</div></div>";
	} ?>

	<!-- ================================== -->
	<!-- =============== Posts ============ --> 
	<!-- ================================== -->
	<div id="posts">
	</div>
	
	<!-- ============================================ -->
	<!-- =============== Menus de gauche ============ --> 
	<!-- ============================================ -->
	 
	<div id="categories" class="boxed">
		<h2 class="heading">Actions</h2>
		<div class="content"><ul>
			<li> <img src='images/home_64.png' alt="Informations diverses" title="Informations diverses" width="32"  onclick='news();return false;'  style='cursor:pointer'/>
				 <img src='images/pencil_64.png' alt="Créer une nouvelle requête" title="Créer une nouvelle requête" width="32"  onclick='new_query();return false;'  style='cursor:pointer'/>
			     <img src='images/buy_64.png' alt="Requêtes mémorisées" title="Requêtes mémorisées" width="32" onclick='get_histo();return false;' style='cursor:pointer'/>
			     <img src='images/help_64.png' alt="Aide sur l'application" title="Aide sur l'application" width="32" onclick="aides();return false;" style='cursor:help'/>
			</li>
			</ul>
		</div>
	</div>
	
	<div id="bases" class="boxed">
		<h2 class="heading">Bases disponibles</h2>
		<div class="content">
			<ul>
		<?php foreach($listeNoms as $nom) {
				$h = htmlspecialchars($nom, ENT_QUOTES);
				$j = htmlspecialchars(json_encode($nom), ENT_QUOTES);
				echo "<li> <a href='#' onClick='new Effect.Highlight(this); db($j);return false;' style='cursor:pointer'>$h</a></li>";
			}?>
			</ul>
		</div>
	</div>

	<div id="blogroll" class="boxed">
		<h2 class="heading">Contact</h2>
		<div class="content">
		<ul>
		  <li> <?php echo "<a href='mailto:emmanuel.desmontils@univ-nantes.fr?subject=SQLSandbox $version - question '>"; ?>
		       <img src="images/letter_64.png" alt="Courriel" title="Courriel" width="32"/></a> 
		       <a href="http://www.desmontils.net/">
		       <img src="images/globe_64.png" alt="Page Web" title="Page Web" width="32"/></a></li>
		</ul>
		</div>
	</div>
	
	<div id="pages" class="boxed">
		<h2 class="heading">Mentions</h2>
		<div class="content">
			<ul>
				<li><img src='images/info_64.png' alt="À propos de SQL" title="À propos de SQL" width="32" onclick="apropos();return false;" style='cursor:pointer'/>
				    <img src='images/shield_64.png' alt="Mentions légales" title="Mentions légales" width="32" onclick="mentions();return false;" style='cursor:pointer'/>
				    
				</li>
			</ul>
		</div>
	</div>
	
	<div id="liens" class="boxed">
		<!--h2 class="heading">Liens</h2-->
		<div class="content">
			<div id="footer">
				<p>Copyright &copy; 2010-2026 <a href="http://www.desmontils.net/">E. Desmontils</a>,<br/> Université de Nantes.
				   <br/>Logiciel libre sous <a href="https://www.gnu.org/licenses/gpl-3.0.fr.html">licence GPL v3</a>
				   <br/>Designed by <a href="http://www.freecsstemplates.org/"><strong>Free CSS Templates</strong></a> 
				   <br/><a href="http://validator.w3.org/check/referer" title="This page validates as XHTML 1.0 Transitional">Valid <abbr title="eXtensible HyperText Markup Language">XHTML</abbr></a>
				   <br/><a href="#" onclick="mentions();return false;" style='cursor:pointer'>Autres mentions légales</a>
				</p>
			</div>
		</div>
	</div>

</div>


</body>
</html>
