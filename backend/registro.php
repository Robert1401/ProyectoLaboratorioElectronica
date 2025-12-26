<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(204); exit; }
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(405);
  echo json_encode(["success"=>false,"message"=>"Método no permitido"]); exit;
}

$host = "127.0.0.1";
$user = "Laura";
$pass = "root";
$db   = "Laboratorio_Electronica";

$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
  http_response_code(500);
  echo json_encode(["success"=>false,"message"=>"❌ Error al conectar a la base de datos."]); exit;
}

$in = json_decode(file_get_contents("php://input"), true);
$req = ["numeroControl","nombre","apellidoPaterno","apellidoMaterno","carrera","clave"];
foreach ($req as $f) {
  if (!isset($in[$f]) || trim((string)$in[$f]) === "") {
    echo json_encode(["success"=>false,"message"=>"⚠️ Falta el campo '$f'."]); exit;
  }
}

/* === Validaciones === */
$numeroControlRaw = trim((string)$in["numeroControl"]);
if (!preg_match('/^\d{8}$/', $numeroControlRaw)) {
  echo json_encode(["success"=>false,"message"=>"⚠️ El número de control debe tener exactamente 8 dígitos."]); exit;
}
$numeroControl   = (int)$numeroControlRaw;
$nombre          = trim((string)$in["nombre"]);
$apellidoPaterno = trim((string)$in["apellidoPaterno"]);
$apellidoMaterno = trim((string)$in["apellidoMaterno"]);
$idCarrera       = (int)$in["carrera"];
$clavePlano      = (string)$in["clave"];

/* === Límite de contraseña: 10 === */
if (strlen($clavePlano) > 10) {
  echo json_encode(["success"=>false,"message"=>"❌ La contraseña no puede exceder 10 caracteres."]); exit;
}

/* === Obtener roles === */
$idRolAlumno = $idRolDocente = $idRolAux = null;
$stmt = $mysqli->prepare("SELECT id_Rol, nombre FROM Roles WHERE nombre IN ('Alumno','Docente','Auxiliar')");
$stmt->execute(); $res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  if ($row["nombre"] === "Alumno")  $idRolAlumno  = (int)$row["id_Rol"];
  if ($row["nombre"] === "Docente") $idRolDocente = (int)$row["id_Rol"];
  if ($row["nombre"] === "Auxiliar")$idRolAux     = (int)$row["id_Rol"];
}
$stmt->close();
if (!$idRolAlumno) { echo json_encode(["success"=>false,"message"=>"❌ Falta el rol 'Alumno' en Roles."]); exit; }

/* === Carrera válida === */
$stmt = $mysqli->prepare("SELECT 1 FROM Carreras WHERE id_Carrera = ? LIMIT 1");
$stmt->bind_param("i", $idCarrera);
$stmt->execute(); $stmt->store_result();
if ($stmt->num_rows === 0) { $stmt->close(); echo json_encode(["success"=>false,"message"=>"❌ La carrera no existe."]); exit; }
$stmt->close();

/* === Reglas de duplicados ===
     - numeroControl no debe existir en Personas (Alumno/Docente/Auxiliar) ni en Usuarios
     - apellidoPaterno no debe existir en Personas con rol Alumno
*/
$ncTaken = false;

// Personas (cualquier rol de interés)
if ($idRolDocente && $idRolAux) {
  $stmt = $mysqli->prepare("
    SELECT 1
    FROM Personas
    WHERE numeroControl = ?
      AND id_Rol IN (?, ?, ?)
    LIMIT 1
  ");
  $stmt->bind_param("iiii", $numeroControl, $idRolAlumno, $idRolDocente, $idRolAux);
} else {
  // Fallback si no existen todos los roles
  $stmt = $mysqli->prepare("SELECT 1 FROM Personas WHERE numeroControl = ? LIMIT 1");
  $stmt->bind_param("i", $numeroControl);
}
$stmt->execute(); $stmt->store_result();
if ($stmt->num_rows > 0) $ncTaken = true;
$stmt->close();

// Usuarios
if (!$ncTaken) {
  $stmt = $mysqli->prepare("SELECT 1 FROM Usuarios WHERE numeroControl = ? LIMIT 1");
  $stmt->bind_param("i", $numeroControl);
  $stmt->execute(); $stmt->store_result();
  if ($stmt->num_rows > 0) $ncTaken = true;
  $stmt->close();
}
if ($ncTaken) { echo json_encode(["success"=>false,"message"=>"❌ El número de control ya está en uso."]); exit; }

// Apellido paterno duplicado en ALUMNOS
$stmt = $mysqli->prepare("
  SELECT 1
  FROM Personas
  WHERE id_Rol = ?
    AND apellidoPaterno = ?
  LIMIT 1
");
$stmt->bind_param("is", $idRolAlumno, $apellidoPaterno);
$stmt->execute(); $stmt->store_result();
if ($stmt->num_rows > 0) {
  $stmt->close();
  echo json_encode(["success"=>false,"message"=>"❌ Apellido paterno ya registrado en ALUMNOS."]); exit;
}
$stmt->close();

/* === Hash y transacción === */
$hash = password_hash($clavePlano, PASSWORD_BCRYPT);
$idEstadoActivo = 1;

$mysqli->begin_transaction();
try {
  // Personas
  $sql = "INSERT INTO Personas (numeroControl, id_Rol, id_Estado, nombre, apellidoPaterno, apellidoMaterno)
          VALUES (?, ?, ?, ?, ?, ?)";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("iiisss", $numeroControl, $idRolAlumno, $idEstadoActivo, $nombre, $apellidoPaterno, $apellidoMaterno);
  if (!$stmt->execute()) throw new Exception("personas");
  $stmt->close();

  // CarrerasAlumnos
  $sql = "INSERT INTO CarrerasAlumnos (numeroControl, id_Carrera) VALUES (?, ?)";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("ii", $numeroControl, $idCarrera);
  if (!$stmt->execute()) throw new Exception("carrerasalumnos");
  $stmt->close();

  // Usuarios
  $sql = "INSERT INTO Usuarios (id_Estado, numeroControl, Clave) VALUES (?, ?, ?)";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("iis", $idEstadoActivo, $numeroControl, $hash);
  if (!$stmt->execute()) throw new Exception("usuarios");
  $stmt->close();

  $mysqli->commit();
  echo json_encode(["success"=>true,"message"=>"✅ Registro exitoso."]);
} catch (Exception $e) {
  $mysqli->rollback();
  echo json_encode(["success"=>false,"message"=>"❌ No se pudo registrar."]);
}

$mysqli->close();
