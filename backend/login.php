<?php
// CORS + JSON
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Config BD
$host = "127.0.0.1";
$user = "root";
$pass = "root";
$db   = "Laboratorio_Electronica";

// Conexión
$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
  echo json_encode(["success"=>false,"message"=>"❌ Error de conexión a BD"]); exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(["success"=>false,"message"=>"Método no permitido"]); exit;
}

// Entrada JSON
$input = json_decode(file_get_contents("php://input"), true);
$numeroControl = trim($input['numeroControl'] ?? '');
$passwordInput = $input['password'] ?? '';

if ($numeroControl === '' || $passwordInput === '') {
  echo json_encode(["success"=>false,"message"=>"⚠️ Campos vacíos"]); exit;
}

// Debe ser numérico y con 6 a 8 dígitos (el ajuste exacto por rol se hace después)
// Debe ser numérico
if (!preg_match('/^\d+$/', $numeroControl)) {
  echo json_encode(["success"=>false,"message"=>"🔎 El número de control debe ser numérico."]); exit;
}

$len = strlen($numeroControl);

// 1) Muy pocos dígitos (<4)
if ($len < 4) {
  echo json_encode(["success"=>false,"message"=>"🔎 Faltan dígitos."]); exit;
}

// 2) 5–7 dígitos → no válido para ningún rol (ni auxiliar ni alumno)
if ($len >= 5 && $len <= 7) {
  echo json_encode(["success"=>false,"message"=>"🔎 Debe tener 4 (auxiliar) o 8 (alumno) dígitos."]); exit;
}

// 3) Más de 8 dígitos
if ($len > 8) {
  echo json_encode(["success"=>false,"message"=>"🔎 Te pasaste de dígitos."]); exit;
}



/*
  Esquema actual:
  Usuarios(id_Estado, numeroControl PK(unique), Clave)
  Personas(numeroControl PK, id_Rol, id_Estado, nombre, ...)
  Roles(id_Rol, nombre)
*/
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

$numeroControlStr = $numeroControl;              // conserva como string para medir longitud
$len = strlen($numeroControlStr);

// Rol tal cual viene y versión minúscula
$rolBruto = trim($row['nombreRol'] ?? '');
$rol = mb_strtolower($rolBruto, 'UTF-8');

// Validación por rol real
if ($rol === 'auxiliar' && $len !== 4) {
  echo json_encode(["success"=>false,"message"=> ($len < 4 ? "🔎 Faltan dígitos." : "🔎 Te pasaste de dígitos.") ]);
  $mysqli->close(); exit;
}

if ($rol === 'alumno' && $len !== 8) {
  echo json_encode(["success"=>false,"message"=> ($len < 8 ? "🔎 Faltan dígitos." : "🔎 Te pasaste de dígitos.") ]);
  $mysqli->close(); exit;
}


// Verifica contraseña
$hash = $row['hash'] ?? '';
if ($hash === '' || !password_verify($passwordInput, $hash)) {
  echo json_encode(["success"=>false,"message"=>"❌ Credenciales incorrectas"]);
  $mysqli->close(); exit;
}

// (Opcional) Bloqueo por estado
if ((int)$row['estadoUsuario'] !== 1 || (int)$row['estadoPersona'] !== 1) {
  echo json_encode(["success"=>false,"message"=>"⛔ Usuario inactivo."]); $mysqli->close(); exit;
}

echo json_encode([
  "success"       => true,
  "message"       => "✅ Bienvenido ".$row['nombrePersona'],
  "rol"           => $rol,
  "rolNombreFull" => $rolBruto,
  "nombre"        => $row['nombrePersona'],
  "numeroControl" => (int)$row['numeroControl'],
]);

$mysqli->close();
