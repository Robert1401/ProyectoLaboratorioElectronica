<?php
header("Content-Type: application/json");

$servername = "127.0.0.1";
$username = "root";
$password = "root"; // tu contraseña
$dbname = "Laboratorio_Electronica";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Error de conexión a la base de datos"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$numeroControl = $data['numeroControl'];
$nombre = $data['nombre'];
$apellidoPaterno = $data['apellidoPaterno'];
$apellidoMaterno = $data['apellidoMaterno'];
$materia = $data['materia'];
$carrera = $data['carrera'];
$clave = $data['clave']; // usar la contraseña que el usuario puso
$estado = true;

// Verificar si el alumno ya está registrado
$check_sql = "SELECT NumeroControl FROM Alumnos WHERE NumeroControl = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $numeroControl);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "⚠️ Ya existe un alumno con ese número de control."]);
    $check_stmt->close();
    $conn->close();
    exit;
}
$check_stmt->close();

// Insertar nuevo alumno
$sql = "INSERT INTO Alumnos 
(NumeroControl, Materias_ClaveMateria, Carreras_ClaveCarrera, Nombre, ApellidoPaterno, ApellidoMaterno, Estado, Clave)
VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isssssis", $numeroControl, $materia, $carrera, $nombre, $apellidoPaterno, $apellidoMaterno, $estado, $clave);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "✅ Registro exitoso. ¡Bienvenido!"]);
} else {
    echo json_encode(["success" => false, "message" => "⚠️ Error al registrar. Verifica los datos."]);
}

$stmt->close();
$conn->close();
?>
