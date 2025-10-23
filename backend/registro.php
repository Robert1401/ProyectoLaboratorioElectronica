<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$host = "127.0.0.1";
$user = "root";
$pass = "root";
$db   = "Laboratorio_Electronica";

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
  echo json_encode(["success"=>false,"message"=>"❌ Error al conectar a la BD"]); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(["success"=>false,"message"=>"Método no permitido"]); exit;
}

$in = json_decode(file_get_contents("php://input"), true);
$req = ["numeroControl","nombre","apellidoPaterno","apellidoMaterno","carrera","clave"];
foreach ($req as $f) {
  if (empty($in[$f])) {
    echo json_encode(["success"=>false,"message"=>"⚠️ Falta el campo '$f'."]); exit;
  }
}

$numeroControl   = (int)trim($in["numeroControl"]);
$nombre          = trim($in["nombre"]);
$apellidoPaterno = trim($in["apellidoPaterno"]);
$apellidoMaterno = trim($in["apellidoMaterno"]);
$idCarrera       = (int)$in["carrera"];
$clavePlano      = (string)$in["clave"];

// 1) numeroControl duplicado en Personas
$stmt = $mysqli->prepare("SELECT 1 FROM Personas WHERE numeroControl = ?");
$stmt->bind_param("i", $numeroControl);
$stmt->execute(); $stmt->store_result();
if ($stmt->num_rows > 0) { $stmt->close();
  echo json_encode(["success"=>false,"message"=>"❌ El número de control ya está registrado en Personas."]); exit;
}
$stmt->close();

// 2) número de control duplicado en Usuarios (por consistencia con UNIQUE_USUARIOS)
$stmt = $mysqli->prepare("SELECT 1 FROM Usuarios WHERE numeroControl = ?");
$stmt->bind_param("i", $numeroControl);
$stmt->execute(); $stmt->store_result();
if ($stmt->num_rows > 0) { $stmt->close();
  echo json_encode(["success"=>false,"message"=>"❌ El número de control ya tiene credenciales en Usuarios."]); exit;
}
$stmt->close();

// 3) carrera válida
$stmt = $mysqli->prepare("SELECT 1 FROM Carreras WHERE id_Carrera = ?");
$stmt->bind_param("i", $idCarrera);
$stmt->execute(); $stmt->store_result();
if ($stmt->num_rows === 0) { $stmt->close();
  echo json_encode(["success"=>false,"message"=>"❌ La carrera no existe."]); exit;
}
$stmt->close();

// 4) rol 'Alumno'
$stmt = $mysqli->prepare("SELECT id_Rol FROM Roles WHERE nombre='Alumno' LIMIT 1");
$stmt->execute(); $stmt->bind_result($idRolAlumno);
if (!$stmt->fetch()) { $stmt->close();
  echo json_encode(["success"=>false,"message"=>"❌ Falta el rol 'Alumno' en Roles."]); exit;
}
$stmt->close();

// 5) hash de contraseña (BCRYPT)
$hash = password_hash($clavePlano, PASSWORD_BCRYPT);
// $hash = password_hash($clavePlano, PASSWORD_BCRYPT, ['cost' => 11]); // opcional

$idEstadoActivo = 1;

$mysqli->begin_transaction();
try {
  // Personas
  $sql = "INSERT INTO Personas (numeroControl, id_Rol, id_Estado, nombre, apellidoPaterno, apellidoMaterno)
          VALUES (?, ?, ?, ?, ?, ?)";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("iiisss", $numeroControl, $idRolAlumno, $idEstadoActivo, $nombre, $apellidoPaterno, $apellidoMaterno);
  if (!$stmt->execute()) throw new Exception($stmt->error);
  $stmt->close();

  // CarrerasAlumnos
  $sql = "INSERT INTO CarrerasAlumnos (numeroControl, id_Carrera) VALUES (?, ?)";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("ii", $numeroControl, $idCarrera);
  if (!$stmt->execute()) throw new Exception($stmt->error);
  $stmt->close();

  // Usuarios (sin columna Usuario; solo estado, número de control y hash)
  $sql = "INSERT INTO Usuarios (id_Estado, numeroControl, Clave) VALUES (?, ?, ?)";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("iis", $idEstadoActivo, $numeroControl, $hash);
  if (!$stmt->execute()) throw new Exception($stmt->error);
  $stmt->close();

  $mysqli->commit();
  echo json_encode(["success"=>true,"message"=>"✅ Registro exitoso."]);
} catch (Exception $e) {
  $mysqli->rollback();
  echo json_encode(["success"=>false,"message"=>"❌ No se pudo registrar: ".$e->getMessage()]);
}
$mysqli->close();
