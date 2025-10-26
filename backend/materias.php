<?php
/****************************************************
 *  API Materias (CRUD) — SIN lógica de activo/inactivo
 ****************************************************/

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$DB = [
  'host' => '127.0.0.1',
  'user' => 'root',
  'pass' => 'root', // ajusta si tu clave es distinta
  'name' => 'Laboratorio_Electronica',
];

function json_ok(array $data = [], int $code = 200): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function json_err(string $msg, int $code = 400): void {
  http_response_code($code);
  echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}
function read_json_body(): array {
  $raw  = file_get_contents("php://input") ?: '';
  $data = json_decode($raw, true);
  if (!is_array($data)) { parse_str($raw, $data); }
  return is_array($data) ? $data : [];
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $mysqli = new mysqli($DB['host'], $DB['user'], $DB['pass'], $DB['name']);
  $mysqli->set_charset("utf8mb4");
} catch (Throwable $e) {
  json_err("Error de conexión a la base de datos.", 500);
}

$method = $_SERVER["REQUEST_METHOD"] ?? 'GET';

// GET ?tipo=carreras (opcional)
if ($method === "GET" && isset($_GET["tipo"]) && $_GET["tipo"] === "carreras") {
  try {
    $res = $mysqli->query("SELECT id_Carrera, nombre FROM Carreras ORDER BY nombre");
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    json_ok($rows);
  } catch (Throwable $e) {
    json_err("No se pudieron cargar carreras.", 500);
  }
}

try {
  switch ($method) {
    // LISTAR
    case "GET": {
      $sql = "SELECT
                m.id_Materia,
                m.nombre AS materia,
                m.id_Carrera,
                m.id_Estado,
                c.nombre AS carrera
              FROM Materias m
              LEFT JOIN Carreras c ON c.id_Carrera = m.id_Carrera
              ORDER BY c.nombre, m.nombre";
      $res  = $mysqli->query($sql);
      $rows = $res->fetch_all(MYSQLI_ASSOC);
      json_ok($rows);
    }

    // CREAR
    case "POST": {
      $in         = read_json_body();
      $nombre     = trim($in["nombre"]     ?? "");
      $id_Carrera = (int)($in["id_Carrera"] ?? 0);
      if ($nombre === "" || $id_Carrera <= 0) json_err("Nombre e id_Carrera son obligatorios.", 400);

      $stmt = $mysqli->prepare(
        "INSERT INTO Materias (id_Estado, id_Carrera, nombre) VALUES (1, ?, ?)"
      );
      $stmt->bind_param("is", $id_Carrera, $nombre);

      try {
        $stmt->execute();
        json_ok([
          "mensaje"    => "✅ Materia guardada",
          "id_Materia" => $stmt->insert_id,
          "nombre"     => $nombre,
          "id_Carrera" => $id_Carrera,
          "id_Estado"  => 1
        ], 201);
      } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) json_err("❗ Esa materia ya existe en esa carrera.", 409);
        json_err("No se pudo guardar la materia.", 500);
      }
    }

    // ACTUALIZAR (editar nombre/carrera) — SIN toggle
    case "PUT": {
      $in         = read_json_body();
      $id_Materia = (int)($in["id_Materia"] ?? 0);
      $nombre     = trim($in["nombre"] ?? "");
      $id_Carrera = (int)($in["id_Carrera"] ?? 0);

      if ($id_Materia <= 0 || $nombre === "" || $id_Carrera <= 0) {
        json_err("Datos incompletos para actualizar.", 400);
      }

      $stmt = $mysqli->prepare(
        "UPDATE Materias SET nombre = ?, id_Carrera = ? WHERE id_Materia = ?"
      );
      $stmt->bind_param("sii", $nombre, $id_Carrera, $id_Materia);

      try {
        $stmt->execute();
        json_ok(["mensaje" => "✏️ Materia actualizada"]);
      } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) json_err("❗ Ese nombre ya existe en esa carrera.", 409);
        json_err("No se pudo actualizar la materia.", 500);
      }
    }

    // DELETE físico
   // ... (encabezados y helpers iguales)

// En el switch($method), dentro de "DELETE" REEMPLAZA por esto:
case "DELETE": {
  // 1) Purga de INACTIVAS si viene ?inactive=1
  if (isset($_GET["inactive"]) && (int)$_GET["inactive"] === 1) {
    // id_Estado: 1=Activo, otros = inactivos
    $stmt = $mysqli->prepare("DELETE FROM Materias WHERE id_Estado <> 1");
    try {
      $stmt->execute();
      $deleted = $stmt->affected_rows;
      json_ok(["mensaje"=>"Purgadas inactivas", "eliminadas"=>$deleted]);
    } catch (mysqli_sql_exception $e) {
      json_err("No se pudo purgar inactivas.", 500);
    }
  }

  // 2) Borrado individual por id_Materia
  $in         = read_json_body();
  $id_Materia = isset($_GET["id_Materia"])
    ? (int)$_GET["id_Materia"]
    : (int)($in["id_Materia"] ?? 0);

  if ($id_Materia <= 0) json_err("ID inválido.", 400);

  $stmt = $mysqli->prepare("DELETE FROM Materias WHERE id_Materia = ?");
  $stmt->bind_param("i", $id_Materia);

  try {
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
      json_ok(["mensaje" => "Nada para eliminar (ID no encontrado)."]);
    } else {
      json_ok(["mensaje" => "🗑️ Materia eliminada"]);
    }
  } catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1451) {
      json_err("No se puede eliminar: hay registros relacionados.", 409);
    }
    json_err("No se pudo eliminar la materia.", 500);
  }
}


    default:
      json_err("Método no permitido.", 405);
  }
} catch (Throwable $e) {
  json_err("Error inesperado.", 500);
} finally {
  if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
  }
}
