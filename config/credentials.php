<?php
// IP Info
$ip_access_token = decode('ip_access');

// HelloAsso
$ha_admins = ['admin', 'marvolo'];
$ha_client_secret = decode('ha_client');
$ha_client_id = decode('ha_secret');

// Paheko
$paheko_admins = ['admin', 'marvolo'];
$paheko_client_secret = decode('paheko_secret');
$paheko_client_id = decode('paheko_id');
$paheko_address = "https://arcensiel.paheko.frama.space/api";

// Server DB connection settings
$mysql_secret = decode('mysql_root_secret');
$host = 'localhost';
$db = 'arcensiel';
$db_main_user = 'arcensiel';
$db_main_pass = decode('database_arcensiel_secret');
$db_mail_user = 'mailer';
$db_mail_pass = decode('database_mail_secret');
$charset = 'utf8mb4';

// Password token
$password_token = decode('salt');
?>