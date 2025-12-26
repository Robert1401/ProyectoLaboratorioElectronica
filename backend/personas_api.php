<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(204); exit; }

$host = "127.0.0.1"; $user = "root"; $pass = "root"; $db = "Laboratorio_Electronica";
$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) { http_response_code(500); echo json_encode(["success"=>false,"message"=>"❌ Error al conectar a la base de datos."]); exit; }

$action = $_GET["action"] ?? $_POST["action"] ?? "";
function ok($payload = []){ echo json_encode(["success"=>true] + $payload); exit; }
function fail($msg, $code=200){ http_response_code($code); echo json_encode(["success"=>false,"message"=>$msg]); exit; }
function clean($s){ return trim((string)$s); }
function getRoleId(mysqli $db, string $roleName){
  $stmt = $db->prepare("SELECT id_Rol FROM Roles WHERE nombre = ? LIMIT 1");
  $stmt->bind_param("s", $roleName); $stmt->execute(); $stmt->bind_result($id);
  $ok = $stmt->fetch(); $stmt->close(); return $ok ? (int)$id : 0;
}
function digitsByTipo(string $tipo){ return ($tipo === "Alumno") ? 8 : 4; }

if ($action === "list" && $_SERVER["REQUEST_METHOD"] === "GET") {
  $sql = "SELECT p.numeroControl, p.nombre, p.apellidoPaterno, p.apellidoMaterno, r.nombre AS tipo
          FROM Personas p JOIN Roles r ON r.id_Rol = p.id_Rol
          ORDER BY p.numeroControl";
  $rs = $mysqli->query($sql); $rows = [];
  if ($rs) while ($row = $rs->fetch_assoc()) $rows[] = $row;
  ok(["data"=>$rows]);
}

$in = json_decode(file_get_contents("php://input"), true); if (!is_array($in)) $in = [];

/* -------- CREATE -------- */
if ($action === "create" && $_SERVER["REQUEST_METHOD"] === "POST") {
  $tipo     = clean($in["tipo"] ?? "");
  $controlR = clean($in["control"] ?? "");
  $nombre   = clean($in["nombre"] ?? "");
  $paterno  = clean($in["paterno"] ?? "");
  $materno  = clean($in["materno"] ?? "");
  $pwd      = (string)($in["password"] ?? "");

  if (!$tipo || !$controlR || !$nombre || !$paterno || !$materno || !$pwd) fail("⚠️ Faltan campos.");
  $digits = digitsByTipo($tipo);
  if (!preg_match('/^\d+$/', $controlR) || strlen($controlR) !== $digits) fail("⚠️ El número de control para $tipo debe tener exactamente $digits dígitos.");
  if (!preg_match('/^\d{10}$/', $pwd)) fail("⚠️ La contraseña debe tener exactamente 10 dígitos.");
  $control = (int)$controlR;

  // Unicidad: Nombre + Apellido Paterno (case-insensitive, trim)
  $stmt = $mysqli->prepare("
    SELECT 1
    FROM Personas
    WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?))
      AND LOWER(TRIM(apellidoPaterno)) = LOWER(TRIM(?))
    LIMIT 1
  ");
  $stmt->bind_param("ss", $nombre, $paterno);
  $stmt->execute(); $stmt->store_result();
  if ($stmt->num_rows > 0) { $stmt->close(); fail("⚠ Ya existe alguien con ese Nombre + Apellido Paterno."); }
  $stmt->close();

  // Número de control único (Personas y Usuarios)
  $stmt = $mysqli->prepare("SELECT 1 FROM Personas WHERE numeroControl = ? LIMIT 1");
  $stmt->bind_param("i", $control); $stmt->execute(); $stmt->store_result();
  if ($stmt->num_rows > 0) { $stmt->close(); fail("❌ El número de control ya está en uso."); }
  $stmt->close();

  $stmt = $mysqli->prepare("SELECT 1 FROM Usuarios WHERE numeroControl = ? LIMIT 1");
  $stmt->bind_param("i", $control); $stmt->execute(); $stmt->store_result();
  if ($stmt->num_rows > 0) { $stmt->close(); fail("❌ El número de control ya está en uso."); }
  $stmt->close();

  $idRol = getRoleId($mysqli, $tipo); if (!$idRol) fail("❌ El rol '$tipo' no existe en Roles.");
  $idEstado = 1; $hash = password_hash($pwd, PASSWORD_BCRYPT);

  $mysqli->begin_transaction();
  try {
    $stmt = $mysqli->prepare("INSERT INTO Personas (numeroControl, id_Rol, id_Estado, nombre, apellidoPaterno, apellidoMaterno) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("iiisss", $control, $idRol, $idEstado, $nombre, $paterno, $materno);
    if (!$stmt->execute()) throw new Exception("personas"); $stmt->close();

    $stmt = $mysqli->prepare("INSERT INTO Usuarios (id_Estado, numeroControl, Clave) VALUES (?,?,?)");
    $stmt->bind_param("iis", $idEstado, $control, $hash);
    if (!$stmt->execute()) throw new Exception("usuarios"); $stmt->close();

    $mysqli->commit(); ok(["message"=>"✅ Persona creada."]);
  } catch (Exception $e) {
    $mysqli->rollback(); fail("❌ No se pudo crear el registro.");
  }
}

/* -------- UPDATE -------- */
if ($action === "update" && $_SERVER["REQUEST_METHOD"] === "POST") {
  $tipo     = clean($in["tipo"] ?? "");
  $controlR = clean($in["control"] ?? "");
  $nombre   = clean($in["nombre"] ?? "");
  $paterno  = clean($in["paterno"] ?? "");
  $materno  = clean($in["materno"] ?? "");
  $pwd      = (string)($in["password"] ?? "");

  if (!$controlR) fail("⚠️ Falta el número de control.");
  $tValid = $tipo ?: "Alumno";
  $digits = digitsByTipo($tValid);
  if (!preg_match('/^\d+$/', $controlR) || strlen($controlR) !== $digits) fail("⚠️ El número de control para $tValid debe tener exactamente $digits dígitos.");
  $control = (int)$controlR;

  // Debe existir
  $stmt = $mysqli->prepare("SELECT 1 FROM Personas WHERE numeroControl = ? LIMIT 1");
  $stmt->bind_param("i", $control); $stmt->execute(); $stmt->store_result();
  if ($stmt->num_rows === 0) { $stmt->close(); fail("❌ No existe ese número de control."); }
  $stmt->close();

  // Unicidad (Nombre + Paterno) excluyendo a sí mismo
  if ($nombre !== "" && $paterno !== "") {
    $stmt = $mysqli->prepare("
      SELECT 1
      FROM Personas
      WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?))
        AND LOWER(TRIM(apellidoPaterno)) = LOWER(TRIM(?))
        AND numeroControl <> ?
      LIMIT 1
    ");
    $stmt->bind_param("ssi", $nombre, $paterno, $control);
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) { $stmt->close(); fail("⚠ Ya existe alguien con ese Nombre + Apellido Paterno."); }
    $stmt->close();
  }

  $mysqli->begin_transaction();
  try {
    if ($tipo) {
      $idRol = getRoleId($mysqli, $tipo); if (!$idRol) { $mysqli->rollback(); fail("❌ El rol '$tipo' no existe."); }
      $stmt = $mysqli->prepare("UPDATE Personas SET id_Rol=?, nombre=?, apellidoPaterno=?, apellidoMaterno=? WHERE numeroControl=?");
      $stmt->bind_param("isssi", $idRol, $nombre, $paterno, $materno, $control);
    } else {
      $stmt = $mysqli->prepare("UPDATE Personas SET nombre=?, apellidoPaterno=?, apellidoMaterno=? WHERE numeroControl=?");
      $stmt->bind_param("sssi", $nombre, $paterno, $materno, $control);
    }
    if (!$stmt->execute()) throw new Exception("personas_update"); $stmt->close();

    if ($pwd !== "") {
      if (!preg_match('/^\d{10}$/', $pwd)) { $mysqli->rollback(); fail("⚠️ La contraseña debe tener exactamente 10 dígitos."); }
      $hash = password_hash($pwd, PASSWORD_BCRYPT);
      $stmt = $mysqli->prepare("UPDATE Usuarios SET Clave=? WHERE numeroControl=?");
      $stmt->bind_param("si", $hash, $control);
      if (!$stmt->execute()) throw new Exception("usuarios_update");
      $stmt->close();
    }

    $mysqli->commit(); ok(["message"=>"✅ Registro actualizado."]);
  } catch (Exception $e) {
    $mysqli->rollback(); fail("❌ No se pudo actualizar.");
  }
}

/* -------- DELETE -------- */
if ($action === "delete" && $_SERVER["REQUEST_METHOD"] === "POST") {
  $controlR = clean($in["control"] ?? "");
  if (!preg_match('/^\d+$/', $controlR)) fail("⚠️ Número de control inválido.");
  $control = (int)$controlR;

  $mysqli->begin_transaction();
  try {
    $stmt = $mysqli->prepare("DELETE FROM Usuarios WHERE numeroControl=?");
    $stmt->bind_param("i", $control); if (!$stmt->execute()) throw new Exception("usuarios_del"); $stmt->close();

    $stmt = $mysqli->prepare("DELETE FROM Personas WHERE numeroControl=?");
    $stmt->bind_param("i", $control); if (!$stmt->execute()) throw new Exception("personas_del"); $stmt->close();

    $mysqli->commit(); ok(["message"=>"🗑️ Registro eliminado."]);
  } catch (Exception $e) {
    $mysqli->rollback(); fail("❌ No se pudo eliminar.");
  }
}

fail("Acción no válida.", 400);
