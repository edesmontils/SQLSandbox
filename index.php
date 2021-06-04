<?php 
$session_ok = session_start();
setlocale(LC_TIME,'fr_FR');
 //phpinfo();
$debug=false;
$ok = true;

//Récupération des paramètres
require_once('./lib/LibXML.php');
include "./fonctions.php";

// En cas de rechargement de la page, les bases restent... donc on les supprime !
if (isset($_SESSION['debug'])) {
	purge_db();	
	session_unset();
}

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
	
	//Gestion du seuil de vie des copies de base //60 * 60 * 24;
	$seuil = (integer)$config->seuil;
	
	//Gestion des bases
	$listeBase = array();

	//Définitions dans le fichier de config principal
	foreach($config->listeBases->base_de_donnee as $base) { 
		$listeBase[(string)$base['nom']] = array(
			'fichier' => (string)$base->fichier['nom'],
			'prefixe' => (string)$base->fichier['prefixe'],
			'référence' => (string)$base->référence,
			'description' => (string)$base->description->asXML(),
			'tables' => array() //$tables
		);
		if ($debug) echo "<p>Description de ".$listeBase[(string)$base['nom']]['fichier']."</p>";
	}

	// Définitions dans un fichier de config spécialisé/dédié
	foreach($config->listeBases->configBD as $configBD) {
		$BD = new SimpleXMLElement($configBD['localisation'].'/'.$configBD['fichier'],
                                   LIBXML_DTDATTR|LIBXML_DTDLOAD|LIBXML_DTDVALID
                                   |LIBXML_NOBLANKS|LIBXML_NOCDATA,
                                   true);
		$listeBase[(string)$BD['nom']] = array(
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
								'fichier' => $rep.'/'.$fichier.'/'.(string)$BD->fichier['nom'],
								'dbName' => $fichier,
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

	$listeNoms = array_keys($listeBase);
} catch(Exception $e) {
  echo "<p>erreur<br/>Pb (Exception) ! $e<br/>".$libxml->afficheErreurs()
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
$dir=opendir("./tmp");	
while ($nom=readdir($dir)) {
	if (file_exists($nom)) {
		$file = stat($nom);
		$delta = time() - $file['atime'];
		if ((substr($nom,0,6)=='copie_')&&($delta>$seuil)) {
		    if ($debug) echo "<p>Suppression de $nom (ancien de $delta secondes)</p>";
			if (file_exists($nom)) unlink($nom);
		}
	}
}
closedir($dir);

// Mise en place des BD temporaires pour l'utilisation courante (session)
if ($debug) echo "<p>Création des bases temporaires pour cette session</p>";
foreach($listeNoms as $nom) {
	$_SESSION["copie_$nom"]= tempnam('./tmp',"copie_$nom".'_');
	
	//_________________________________________________
	//$listeBase[$nom]['tmp'] = $_SESSION["copie_$nom"]; // Danger pour la sécurité !
	//_________________________________________________
	
	copy($listeBase[$nom]['fichier'],$_SESSION["copie_$nom"]);
	if ($debug) echo "<p>Création de ".$_SESSION["copie_$nom"]."</p>";
	
	$tables = get_tables($nom);
	$listeBase[$nom]['tables'] = $tables;
}
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
    listeBases = <?php echo json_encode($listeBase); ?>;
    noms_bases = <?php echo json_encode($listeNoms); ?>
  </script>
  <script src="./js/bd_base.js" type="text/javascript"></script>
 
</head>

<body onload="init()" onunload="end()" onreload="end()">

<div id="header">
	<h1><?php echo $nom_appli;?></h1>
	<p>Version <?php echo $version;?></p>
</div>

<div id="content">

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
	
	<div id="categories" class="boxed">
		<h2 class="heading">Bases disponibles</h2>
		<div class="content">
			<ul>
		<?php foreach($listeNoms as $nom) echo "<li> <a href='#' onClick='new Effect.Highlight(this); db(\"$nom\");return false;' style='cursor:pointer'> $nom</a></li>";?>
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
	
	<div id="pages" class="boxed">
		<!--h2 class="heading">Liens</h2-->
		<div class="content">
			<div id="footer">
				<p>Copyright &copy; 2010, 2011, 2016 <a href="http://www.desmontils.net/">E. Desmontils</a>,<br/> Université de Nantes. 
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
