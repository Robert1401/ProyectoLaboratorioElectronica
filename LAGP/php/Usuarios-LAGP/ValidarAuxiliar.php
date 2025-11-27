<?php
include("../conexion.php");

$numeroControl = $_POST['numeroControl'] ?? '';
$clave = $_POST['clave'] ?? '';

if (empty($numeroControl) || empty($clave)) {
    echo "Faltan datos";
    exit;
}

// Buscar auxiliar activo
$sql = "SELECT u.Clave 
        FROM Usuarios u
        JOIN Personas p ON u.numeroControl = p.numeroControl
        WHERE u.numeroControl = ? AND p.id_Rol = 1 AND u.id_Estado = 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $numeroControl);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "No existe el auxiliar o está inactivo";
    exit;
}

$row = $result->fetch_assoc();

// Verificar hash
if (password_verify($clave, $row['Clave'])) {
    echo "OK";
} else {
    echo "Contraseña incorrecta";
}

$stmt->close();
$conn->close();
?>
