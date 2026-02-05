<?php
session_start();

// global variables
$serverroot = $_SERVER['DOCUMENT_ROOT'];
require_once $serverroot . "/../config/config.php";
?>

<!DOCTYPE html>
<html>
	<head>
		<title>Mentions légales</title>
		
		<link rel="icon" href="/files/icons/arcensiel.ico">
		<meta name="theme-color" content="">
		<meta name="msapplication-navbutton-color" content="">
		<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<meta property="og:title" content="arcENSiel - Association Queer et LGBT+ de l'ENS de Lyon">
		<meta property="og:type" content="website">
		
		<link rel='stylesheet' type='text/css' href='/files/assets/css/main.css'></link>
		
		<script src='/files/assets/js/main.js'></script>
	</head>	
	
	<body>
	    <?php include($root . '/app/templates/header.php') ?>
		
		<div id='main'>
            <h1>
                Mentions légales
            </h1>

            <h3>
                Éditeur
            </h3>

            <p>
                <strong>Directeur de publication :</strong> Marc ZUCCALLI, président d'arcENSiel.   
            </p>
                
            <p>
                <strong>Hébergement :</strong> ENS de Lyon, 15 parvis René Descartes, BP 7000, 69342 Lyon Cedex 07
            </p>

            <p>
                Le nom de domaine <i>arcensiel.org</i> est déposé chez LeBureau.coop depuis décembre 2025.
            </p>

            <h3>
                Crédits
            </h3>
                Site conçu et réalisé par Marc ZUCCALLI.

                <p><strong>Bibliothèques :</strong> 
                    <ul>
                        <li><a href="https://github.com/showdownjs/showdown">showdown</a></li>
                        <li><a href="https://github.com/pgrabovets/jsonview">jsonview</a> - pgrabovets</li>
                        <li><a href="https://tabulator.info">Tabulator</a> - © Oli Folkerd</li>
                    </ul>
                </p>
                
                <p><strong>Assets :</strong> 
                    <ul>
                        <li>Text Divider by <a href="https://www.vecteezy.com/free-vector/text-divider">Vecteezy</a></li>
                    </ul>
                </p>
                
            <h3>
                Données personnelles
            </h3>
                
                <p>
                    Ce site n’a pas fait l’objet d’une déclaration à la CNIL et s’inscrit dans le cadre de la dispense n°8 découlant de la délibération du CNIL n°2010-229 du 10 juin 2010 (J.O. n°0155 du 7 juillet 2010).
                </p>

                <p>
                    En application de la loi 78_17 du 6 janvier 1978 relative à l’informatique, aux fichiers et aux libertés, ENvertS garantit un droit d’accès, de modification, de rectification et de suppression des données qui concernent ses visiteurs et intervenants. Pour exercer ce droit, il suffit d’exprimer sa demande par courriel.
                </p>

                <p>
                        Il convient cependant de noter que l'association ne conserve pas les données ou coordonnées des messages qui lui sont adressés par courriel. La destruction des données et coordonnées des auteurices intervient une fois la demande traitée.
                </p>
        </div>
        
        <?php include($root . '/app/templates/footer.html') ?>
    </body>
</html>

