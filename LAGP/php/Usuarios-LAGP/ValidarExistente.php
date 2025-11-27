<?php
include("../conexion.php");

$nombre = $_POST['nombre'] ?? '';
$apellidoPaterno = $_POST['apellidoPaterno'] ?? '';
$apellidoMaterno = $_POST['apellidoMaterno'] ?? '';

$stmt = $conn->prepare("
    SELECT id, tabla FROM (
        SELECT id_Profesor AS id, 'Profesores' AS tabla, nombre, apellidoPaterno, apellidoMaterno
        FROM Profesores
        UNION ALL
        SELECT numeroControl AS id, 'Personas' AS tabla, nombre, apellidoPaterno, apellidoMaterno
        FROM Personas
    ) AS t
    WHERE nombre = ? AND apellidoPaterno = ? AND apellidoMaterno = ?
");
$stmt->bind_param("sss", $nombre, $apellidoPaterno, $apellidoMaterno);
$stmt->execute();
$result = $stmt->get_result();
$datos = [];
while($row = $result->fetch_assoc()){
    $datos[] = $row;
}
echo json_encode($datos);
?>
