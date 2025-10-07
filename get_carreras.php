<?php
include "conexion.php";

$sql = "SELECT ClaveCarrera, NombreCarrera FROM Carreras";
$result = $conn->query($sql);

$carreras = [];
while ($row = $result->fetch_assoc()) {
    $carreras[] = $row;
}

echo json_encode($carreras);
$conn->close();
?>
