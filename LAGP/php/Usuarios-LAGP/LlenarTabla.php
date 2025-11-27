<?php
include("../conexion.php"); // Conexión a la BD

header('Content-Type: application/json; charset=utf-8');

$idRol = isset($_POST['id']) ? intval($_POST['id']) : 0;


if ($idRol < 3) {
    $query = "SELECT numeroControl AS id, nombre, apellidoPaterno, apellidoMaterno
              FROM personas
              WHERE id_Rol = $idRol AND id_Estado = 1";
} else {
    $query = "SELECT id_Profesor AS id, nombre, apellidoPaterno, apellidoMaterno
              FROM profesores
              WHERE id_Estado = 1";
}

$result = $conn->query($query);
if (!$result) {
    echo json_encode(["error" => $conn->error]);
    $conn->close();
    exit;
}
$personas = [];

while ($row = $result->fetch_assoc()) {
    $personas[] = $row;
}

echo json_encode($personas);

$conn->close();
?>