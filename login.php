<?php
$servername = "127.0.0.1";  // o la IP/host de tu servidor MySQL
$username = "root";         // usuario de MySQL
$password = "root";             // contraseña de MySQL
$dbname = "universidad";

// Conexión
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Obtener datos del formulario
$nombre = $_POST['nombre'];
$numero_control = $_POST['numero_control'];

// Consulta segura
$sql = "SELECT * FROM alumnos WHERE nombre = ? AND numero_control = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $nombre, $numero_control);
$stmt->execute();
$result = $stmt->get_result();

// Validar resultado
if ($result->num_rows > 0) {
    // ✅ Encontrado → ir a otra página
    header("Location: dashboard.html");
    exit();
} else {
    // ❌ No encontrado → volver al login
    echo "<script>alert('Nombre o número de control incorrecto');
    window.location.href='index.html';</script>";
}

$conn->close();
?>
