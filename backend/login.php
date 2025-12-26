<?php
// ---------------------------------------------
// API LOGIN (POST JSON: {numeroControl, password})
// Responde: { success, message, rol, nombre, numeroControl }
// y se asegura de UTF-8 y CORS
// ---------------------------------------------

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// === CONFIG BD ===
$host = "127.0.0.1";
$user = "root";
$pass = "root";
$db   = "Laboratorio_Electronica";

// === Conexión ===
$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
  echo json_encode(["success"=>false,"message"=>"❌ Error de conexión a BD"]); exit;
}
$mysqli->set_charset("utf8mb4");

// === Solo POST ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(["success"=>false,"message"=>"Método no permitido"]); exit;
}

// === Entrada JSON ===
$input = json_decode(file_get_contents("php://input"), true);
$numeroControl = trim($input['numeroControl'] ?? '');
$passwordInput = (string)($input['password'] ?? '');

// Validaciones rápidas
if ($numeroControl === '' || $passwordInput === '') {
  echo json_encode(["success"=>false,"message"=>"⚠️ Campos vacíos"]); exit;
}
if (!preg_match('/^\d+$/', $numeroControl)) {
  echo json_encode(["success"=>false,"message"=>"🔎 El número de control debe ser numérico."]); exit;
}
$len = strlen($numeroControl);
if ($len < 4) { echo json_encode(["success"=>false,"message"=>"🔎 Faltan dígitos."]); exit; }
if ($len >= 5 && $len <= 7) { echo json_encode(["success"=>false,"message"=>"🔎 Debe tener 4 (auxiliar) o 8 (alumno) dígitos."]); exit; }
if ($len > 8) { echo json_encode(["success"=>false,"message"=>"🔎 Te pasaste de dígitos."]); exit; }

// Esquema (ajusta nombres si difieren):
// Usuarios(numeroControl PK, Clave(hash), id_Estado)
// Personas(numeroControl, id_Rol, id_Estado, nombre)
// Roles(id_Rol, nombre)
$sql = "
  SELECT 
    U.numeroControl,
    U.Clave                 AS hash,
    U.id_Estado             AS estadoUsuario,
    P.nombre                AS nombrePersona,
    P.id_Estado             AS estadoPersona,
    R.nombre                AS nombreRol
  FROM Usuarios U
  INNER JOIN Personas P ON P.numeroControl = U.numeroControl
  INNER JOIN Roles R    ON R.id_Rol       = P.id_Rol
  WHERE U.numeroControl = ?
  LIMIT 1
";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
  echo json_encode(["success"=>false,"message"=>"❌ Error en la preparación de la consulta"]);
  $mysqli->close(); exit;
}

$ncInt = (int)$numeroControl;
$stmt->bind_param("i", $ncInt);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
  echo json_encode(["success"=>false,"message"=>"❌ Número de control no encontrado"]);
  $stmt->close(); $mysqli->close(); exit;
}

$row = $res->fetch_assoc();
$stmt->close();

$rolBruto = trim($row['nombreRol'] ?? '');
$rolLower = mb_strtolower($rolBruto, 'UTF-8');

// Validar longitud vs rol
if ($rolLower === 'auxiliar' && $len !== 4) {
  echo json_encode(["success"=>false,"message"=> ($len < 4 ? "🔎 Faltan dígitos." : "🔎 Te pasaste de dígitos.") ]);
  $mysqli->close(); exit;
}
if ($rolLower === 'alumno' && $len !== 8) {
  echo json_encode(["success"=>false,"message"=> ($len < 8 ? "🔎 Faltan dígitos." : "🔎 Te pasaste de dígitos.") ]);
  $mysqli->close(); exit;
}

// Verificar contraseña
$hash = (string)($row['hash'] ?? '');
if ($hash === '' || !password_verify($passwordInput, $hash)) {
  echo json_encode(["success"=>false,"message"=>"❌ Credenciales incorrectas"]);
  $mysqli->close(); exit;
}

// Bloqueo por estado (1 = activo)
if ((int)$row['estadoUsuario'] !== 1 || (int)$row['estadoPersona'] !== 1) {
  echo json_encode(["success"=>false,"message"=>"⛔ Usuario inactivo."]); $mysqli->close(); exit;
}

// Respuesta OK: devolvemos nombre y rol
echo json_encode([
  "success"       => true,
  "message"       => "✅ Bienvenido ".$row['nombrePersona'],
  "rol"           => $rolLower,             // "alumno" | "auxiliar"
  "rolNombreFull" => $rolBruto,             // por si lo necesitas
  "nombre"        => $row['nombrePersona'], // <— NOMBRE COMPLETO
  "numeroControl" => (string)$row['numeroControl']
], JSON_UNESCAPED_UNICODE);

$mysqli->close();
