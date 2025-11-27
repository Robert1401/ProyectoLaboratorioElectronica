<?php
// BuscarCarrera.php
include("../conexion.php"); // Conexión a la BD
header('Content-Type: application/json; charset=utf-8');

// Leer el número de control desde la petición
$numeroControl = $_POST['numeroControl'] ?? '';

if ($numeroControl === '') {
    echo json_encode(['error' => 'Número de control vacío']);
    exit;
}

// Consulta para obtener la carrera
$sql = "SELECT id_Carrera FROM CarrerasAlumnos WHERE numeroControl = ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $numeroControl);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($fila = $resultado->fetch_assoc()) {
        echo json_encode(['id_Carrera' => $fila['id_Carrera']]);
    } else {
        echo json_encode(['id_Carrera' => null]);
    }

    $stmt->close();
} else {
    echo json_encode(['error' => 'Error al preparar la consulta']);
}

$conn->close();
?>