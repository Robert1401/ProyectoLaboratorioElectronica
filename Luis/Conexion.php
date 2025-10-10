<?php
$host = "localhost";
$usuario = "root";
$contrasena = "";
$baseDatos = "laboratorio_electronica";

$conexion = new mysqli($host, $usuario, $contrasena, $baseDatos);

if ($conexion->connect_error) {
    die("❌ Error al conectar con la base de datos: " . $conexion->connect_error);
} else {
    echo "✅ Conexión exitosa a la base de datos";
}
?>