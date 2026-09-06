<?php
// IP Info
$ip_access_token = decode('ip_access');

// SMTP ENS
$mail_pass = decode('mail_secret');
$mail_id = decode('mail_id');

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
$host = 'localhost';
$db = 'arcensiel';
$user = 'root';
$pass = decode('database_secret');
$charset = 'utf8mb4';

// Password token
$password_token = decode('salt');
?>