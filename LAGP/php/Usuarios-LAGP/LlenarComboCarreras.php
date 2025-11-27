<?php
include("../conexion.php"); // Conexión a la BD

header('Content-Type: application/json; charset=utf-8');

// Consulta solo el nombre de las carreras
$resultado = $conn->query("SELECT id_Carrera, nombre FROM carreras");

$datos = [];
while ($fila = $resultado->fetch_assoc()) {
    $datos[] = $fila;  // Cada fila será un objeto con { "id_Carrera": 1, "nombre": "Ingeniería en Sistemas" }
}

echo json_encode($datos, JSON_UNESCAPED_UNICODE);

$conn->close();
?>