<?php
header('Content-Type: application/json'); // Le decimos que devolverá JSON
include("../Conexion-LAGP.php"); // Archivo donde se conecta a la BD

$sql = "SELECT nombre FROM materias";
$result = $conn->query($sql);

$materias = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $materias[] = $row;
    }
}

// Devolver los datos como JSON
echo json_encode($materias);

$conn->close();
?>
