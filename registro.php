<?php
header("Content-Type: application/json");

// Conexión a la base de datos
$servername = "127.0.0.1";
$username = "root"; // cambia si usas otro usuario
$password = "root";     // cambia si tu MySQL tiene contraseña
$dbname = "Laboratorio_Electronica"; // tu base de datos

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Error de conexión a la base de datos."]);
    exit;
}

// Obtener datos del JSON
$data = json_decode(file_get_contents("php://input"), true);

$numeroControl = $data['numeroControl'];
$nombre = $data['nombre'];
$apellidoPaterno = $data['apellidoPaterno'];
$apellidoMaterno = $data['apellidoMaterno'];
$materia = $data['materia'];
$carrera = $data['carrera'];
$clave = password_hash($data['clave'], PASSWORD_DEFAULT); // Encriptar la contraseña
$estado = true;

// Insertar en la tabla Alumnos
$sql = "INSERT INTO Alumnos 
(NumeroControl, Materias_ClaveMateria, Carreras_ClaveCarrera, Nombre, ApellidoPaterno, ApellidoMaterno, Estado, Clave)
VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isssssbs", $numeroControl, $materia, $carrera, $nombre, $apellidoPaterno, $apellidoMaterno, $estado, $clave);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "✅ Te registraste con éxito"]);
} else {
    echo json_encode(["success" => false, "message" => "⚠️ Error al registrar. Verifica los datos."]);
}

$stmt->close();
$conn->close();
?>
