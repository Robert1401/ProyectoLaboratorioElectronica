<?php
// ===== Encabezados/CORS =====
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ===== Conexión BD =====
$host = "127.0.0.1";
$user = "root";
$pass = "root";
$db   = "Laboratorio_Electronica";

$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
  http_response_code(500);
  echo json_encode(["error" => "Error de conexión a la base de datos."]);
  exit;
}
$mysqli->set_charset("utf8mb4");

// ===== Utilidades =====
function read_json_body() {
  $raw  = file_get_contents("php://input");
  $data = json_decode($raw, true);
  if (!is_array($data)) { parse_str($raw, $data); if (!is_array($data)) $data = []; }
  return $data;
}

// Normaliza nombre en SQL: colapsa espacios y pasa a minúsculas.
// Si tu MySQL < 8 y no tiene REGEXP_REPLACE, usa la comparación simple de TRIM/LOWER como fallback.
function dup_check_sql($field = "nombre") {
  // Intenta con REGEXP_REPLACE (MySQL 8+)
  return "
    REGEXP_REPLACE(TRIM(LOWER($field)), '\\\\s+', ' ')
      =
    REGEXP_REPLACE(TRIM(LOWER(?)),      '\\\\s+', ' ')
  ";
}

$method = $_SERVER["REQUEST_METHOD"];

// ===== Rutas =====
switch ($method) {

  // ---------- GET: listar carreras ----------
  case "GET": {
    $sql = "SELECT id_Carrera, nombre FROM Carreras ORDER BY nombre";
    $res = $mysqli->query($sql);
    if (!$res) { http_response_code(500); echo json_encode(["error" => "No se pudieron cargar carreras."]); break; }
    $rows = [];
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    echo json_encode($rows);
    break;
  }

  // ---------- POST: crear carrera ----------
  case "POST": {
    $in = read_json_body();
    $nombre = trim($in["nombre"] ?? "");
    if ($nombre === "") {
      http_response_code(400);
      echo json_encode(["error" => "El nombre es obligatorio."]);
      break;
    }

    // Verifica duplicado (insensible a mayúsculas/espacios)
    $sqlDup = "SELECT id_Carrera FROM Carreras WHERE " . dup_check_sql("nombre") . " LIMIT 1";
    $stmt = $mysqli->prepare($sqlDup);
    if (!$stmt) { http_response_code(500); echo json_encode(["error"=>"No se pudo preparar verificación de duplicado."]); break; }
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $dup = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($dup) {
      http_response_code(409);
      echo json_encode(["error" => "❗ Esa carrera ya existe."]);
      break;
    }

    // Si tu tabla Carreras tiene id_Estado, la fijamos a 1 (Activo). Si no existe, quitamos ese campo.
    $tieneEstado = false;
    $check = $mysqli->query("SHOW COLUMNS FROM Carreras LIKE 'id_Estado'");
    if ($check && $check->num_rows > 0) $tieneEstado = true;

    if ($tieneEstado) {
      $stmt = $mysqli->prepare("INSERT INTO Carreras (id_Estado, nombre) VALUES (1, ?)");
    } else {
      $stmt = $mysqli->prepare("INSERT INTO Carreras (nombre) VALUES (?)");
    }
    if (!$stmt) { http_response_code(500); echo json_encode(["error"=>"No se pudo preparar inserción."]); break; }
    $stmt->bind_param("s", $nombre);

    if ($stmt->execute()) {
      http_response_code(201);
      echo json_encode([
        "mensaje"    => "✅ Carrera guardada",
        "id_Carrera" => $stmt->insert_id,
        "nombre"     => $nombre
      ]);
    } else {
      if ($mysqli->errno == 1062) {
        http_response_code(409);
        echo json_encode(["error" => "❗ Esa carrera ya existe (índice único)."]);
      } else {
        http_response_code(500);
        echo json_encode(["error" => "No se pudo guardar la carrera."]);
      }
    }
    $stmt->close();
    break;
  }

  // ---------- PUT: actualizar carrera ----------
  case "PUT": {
    $in = read_json_body();
    $id_Carrera = (int)($in["id_Carrera"] ?? 0);
    $nombre     = trim($in["nombre"] ?? "");

    if ($id_Carrera <= 0 || $nombre === "") {
      http_response_code(400);
      echo json_encode(["error" => "Datos incompletos (id_Carrera y nombre son obligatorios)."]);
      break;
    }

    // Verifica duplicado excluyéndose
    $sqlDup = "
      SELECT id_Carrera FROM Carreras
      WHERE " . dup_check_sql("nombre") . " AND id_Carrera <> ?
      LIMIT 1";
    $stmt = $mysqli->prepare($sqlDup);
    if (!$stmt) { http_response_code(500); echo json_encode(["error"=>"No se pudo preparar verificación de duplicado."]); break; }
    $stmt->bind_param("si", $nombre, $id_Carrera);
    $stmt->execute();
    $dup = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($dup) {
      http_response_code(409);
      echo json_encode(["error" => "❗ Ya existe otra carrera con ese nombre."]);
      break;
    }

    $stmt = $mysqli->prepare("UPDATE Carreras SET nombre = ? WHERE id_Carrera = ?");
    if (!$stmt) { http_response_code(500); echo json_encode(["error"=>"No se pudo preparar actualización."]); break; }
    $stmt->bind_param("si", $nombre, $id_Carrera);

    if ($stmt->execute()) {
      if ($stmt->affected_rows === 0) {
        echo json_encode(["mensaje" => "Sin cambios (verifica el ID o el contenido)."]);
      } else {
        echo json_encode(["mensaje" => "✏️ Carrera actualizada"]);
      }
    } else {
      if ($mysqli->errno == 1062) {
        http_response_code(409);
        echo json_encode(["error" => "❗ Esa carrera ya existe (índice único)."]);
      } else {
        http_response_code(500);
        echo json_encode(["error" => "No se pudo actualizar la carrera."]);
      }
    }
    $stmt->close();
    break;
  }

  // ---------- DELETE: borrar carrera ----------
  case "DELETE": {
    $in = read_json_body();
    $id_Carrera = isset($_GET["id_Carrera"]) ? (int)$_GET["id_Carrera"] : (int)($in["id_Carrera"] ?? 0);

    if ($id_Carrera <= 0) {
      http_response_code(400);
      echo json_encode(["error" => "ID inválido."]);
      break;
    }

    $stmt = $mysqli->prepare("DELETE FROM Carreras WHERE id_Carrera = ?");
    if (!$stmt) { http_response_code(500); echo json_encode(["error"=>"No se pudo preparar el borrado."]); break; }
    $stmt->bind_param("i", $id_Carrera);

    if ($stmt->execute()) {
      if ($stmt->affected_rows === 0) {
        echo json_encode(["mensaje" => "Nada para eliminar (ID no encontrado)."]);
      } else {
        echo json_encode(["mensaje" => "🗑️ Carrera eliminada"]);
      }
    } else {
      if ($mysqli->errno == 1451) {
        http_response_code(409);
        echo json_encode(["error" => "No se puede eliminar: hay registros relacionados (por ejemplo Materias que la referencian)."]);
      } else {
        http_response_code(500);
        echo json_encode(["error" => "No se pudo eliminar la carrera."]);
      }
    }
    $stmt->close();
    break;
  }

  default:
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido."]);
}

$mysqli->close();
