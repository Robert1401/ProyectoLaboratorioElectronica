<?php
include "conexion.php";

if (isset($_GET['carrera'])) {
    $carreraID = intval($_GET['carrera']);
    $sql = "SELECT ClaveMateria, NombreMateria FROM Materias WHERE CarreraID = $carreraID";
    $result = $conn->query($sql);
    $materias = [];
    while ($row = $result->fetch_assoc()) {
        $materias[] = $row;
    }
    echo json_encode($materias);
}

$conn->close();
?>
