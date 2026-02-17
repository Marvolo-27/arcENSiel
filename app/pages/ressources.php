<?php
session_start();

// global variables
$serverroot = $_SERVER['DOCUMENT_ROOT'];
require_once $serverroot . "/../config/config.php";

if ($_SESSION['role'] === 'admin') {
    $status = $status ?? 'view';
} else {
    $status = 'view';
}
?>

<!DOCTYPE html>
<html>
	<head>
		<title>Ressources utiles</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="Ressources utiles">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		
		<script src='/files/assets/js/main.js'></script>

		<style>
		</style>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
            <h1>
                Ressources utiles
            </h1>

           <h3>
                Logos
            </h3>
                <ul>
                    <li><span href="/files/uploads/pdf/BF_arcENSiel_2024-2025.pdf" target="_blank_" download>Logo XXXX</span></li>
                </ul>
           
        </div>
        
        <?php include($root . '/app/templates/footer.html') ?>
    </body>
</html>

