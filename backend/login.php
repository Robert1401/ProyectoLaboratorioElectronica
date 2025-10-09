<?php
header('Content-Type: application/json; charset=utf-8');

// Configuración de la base de datos
$servername = "127.0.0.1";
$username = "root";
$password = "root"; // Cambiar según tu configuración
$dbname = "Laboratorio_Electronica";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Error de conexión a la base de datos"]);
    exit;
}

// Obtener datos enviados desde JavaScript
$data = json_decode(file_get_contents("php://input"), true);
$usuario = $data['usuario'] ?? '';
$clave = $data['password'] ?? '';
$rol = $data['rol'] ?? '';

if ($usuario === '' || $clave === '' || $rol === '') {
    echo json_encode(["success" => false, "message" => "Faltan datos"]);
    exit;
}

// 🔹 Validación de rol antes de consultar
$valid = false;
if ($rol === "alumnos" && preg_match("/^\d{4,}$/", $usuario)) {
    $valid = true;
}elseif ($rol === "auxiliar" && preg_match("/^aux/", $usuario)) {
    $valid = true;
}


if (!$valid) {
    echo json_encode(["success" => false, "message" => "❌ Usuario no corresponde al rol seleccionado."]);
    exit;
}

// 🔹 Buscar en la tabla correspondiente según el rol
switch($rol) {
    case "auxiliar":
        $stmt = $conn->prepare("SELECT Nombre, ApellidoPaterno, ApellidoMaterno, Estado 
                                FROM Auxiliares 
                                WHERE Usuario = ? AND Clave = ?");
        $stmt->bind_param("ss", $usuario, $clave);
        break;

    case "alumnos":
        $stmt = $conn->prepare("SELECT Nombre, ApellidoPaterno, ApellidoMaterno, Estado 
                                FROM Alumnos 
                                WHERE NumeroControl = ? AND Clave = ?");
        $stmt->bind_param("is", $usuario, $clave); // 'i' para entero
        break;

    default:
        echo json_encode(["success" => false, "message" => "Rol no reconocido"]);
        exit;
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if ($row['Estado']) {
        echo json_encode([
            "success" => true,
            "rol" => $rol,
            "message" => "✅ Bienvenido, " . $rol . " " . $row['Nombre'] . " " . $row['ApellidoPaterno']
        ]);
    } else {
        echo json_encode(["success" => false, "message" => ucfirst($rol) . " inactivo"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Usuario o clave incorrectos"]);
}

$stmt->close();
$conn->close();
?>
