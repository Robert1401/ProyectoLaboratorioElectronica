<?php
$host = "localhost";
$user = "root";
$pass = "root";
$db   = "laboratorio_electronica";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Conexión fallida");
}
?>
