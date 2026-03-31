<?php
// config.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sistema_votacion');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// 🔴 ZONA HORARIA ARGENTINA
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Establecer zona horaria en MySQL también
$conn->query("SET time_zone = '-03:00'");

session_start();
?>