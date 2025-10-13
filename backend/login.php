<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Configuración de la base de datos
$servername = "localhost";
$username = "root";
$password = "root";
$database = "laboratorio_electronica";

// Conexión
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "❌ Error en la conexión a la base de datos"]);
    exit;
}

// Leer datos enviados
$input = json_decode(file_get_contents("php://input"), true);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
    exit;
}

$usuario = $conn->real_escape_string($input["usuario"] ?? "");
$passwordInput = $input["password"] ?? "";

if (empty($usuario) || empty($passwordInput)) {
    echo json_encode(["success" => false, "message" => "⚠️ Campos vacíos"]);
    exit;
}

// Consultar usuario
$stmt = $conn->prepare("SELECT * FROM Usuarios WHERE usuario = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "❌ Usuario no encontrado"]);
    $stmt->close();
    $conn->close();
    exit;
}

$row = $result->fetch_assoc();
$hash = $row['clave']; // Hash de la base de datos

// Determinar rol y verificar contraseña
$rol = "";
$nombre = "";
$ok = false;

if (!empty($row["numeroControl"])) {
    // Alumno
    $rol = "alumno";
    $ok = password_verify($passwordInput, $hash);
} elseif (!empty($row["numeroTrabajador"])) {
    // Auxiliar
    $rol = "auxiliar";
    if (substr($hash, 0, 4) === '$2y$') {
        // BCRYPT
        $ok = password_verify($passwordInput, $hash);
    } else {
        // SHA-256 antiguo (solo si hay auxiliares antiguos con SHA-256)
        $ok = hash('sha256', $passwordInput) === $hash;
    }
} else {
    $rol = "desconocido";
    $ok = password_verify($passwordInput, $hash);
}

if ($ok) {
    // Obtener nombre según rol
    if ($rol === "alumno") {
        $stmt2 = $conn->prepare("SELECT nombre FROM Alumnos WHERE numeroControl = ?");
        $stmt2->bind_param("i", $row["numeroControl"]);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $alumno = $res2->fetch_assoc();
        $nombre = $alumno["nombre"];
        $stmt2->close();
    } elseif ($rol === "auxiliar") {
        $stmt2 = $conn->prepare("SELECT nombre FROM Auxiliares WHERE numeroTrabajador = ?");
        $stmt2->bind_param("i", $row["numeroTrabajador"]);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $auxiliar = $res2->fetch_assoc();
        $nombre = $auxiliar["nombre"];
        $stmt2->close();
    } else {
        $nombre = $usuario;
    }

    echo json_encode([
        "success" => true,
        "message" => "✅ Bienvenido " . $nombre,
        "rol" => $rol
    ]);
} else {
    echo json_encode(["success" => false, "message" => "❌ Contraseña incorrecta"]);
}

$stmt->close();
$conn->close();
?>
