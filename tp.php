<?php 
$session_ok = session_start();
setlocale(LC_TIME,'fr_FR');
 //phpinfo();
$debug=false;
$ok = true;

//Récupération des paramètres
require_once('./lib/LibXML.php');
include "./fonctions.php";
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

<body onunload="end()" onreload="end()">
<div id="content">
  <div id="posts">
    <?php
        $param1 = $_GET['var1'];
        $split1 = explode('@', $param1);
        $db_name = $split1[1];

        $param2 = $_GET['var2'];
        $split2 = explode('@', $param2);
        $tp_name = $split2[1];

        liste_question($db_name, $tp_name);
      /*
      $split = str_replace("'", "", $split);
      $db_name = $split[1];
      liste_base($db_name);
      */
    ?>
  </div>
</div>
</body>
</html>