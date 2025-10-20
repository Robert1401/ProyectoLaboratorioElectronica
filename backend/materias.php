<?php
/****************************************************
 *  API Materias (CRUD + toggle activo/inactivo)
 *  Corrección: NO escribir en columnas generadas (nombre_norm)
 *  Manejo de duplicados por error 1062 (índice único)
 ****************************************************/

// ---------- Encabezados CORS / JSON ----------
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ---------- Config DB ----------
$DB = [
  'host' => '127.0.0.1',
  'user' => 'root',
  'pass' => 'root', // ajusta si tu clave es distinta
  'name' => 'Laboratorio_Electronica',
];

// ---------- Helpers JSON ----------
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

// ---------- Conexión ----------
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $mysqli = new mysqli($DB['host'], $DB['user'], $DB['pass'], $DB['name']);
  $mysqli->set_charset("utf8mb4");
} catch (Throwable $e) {
  json_err("Error de conexión a la base de datos.", 500);
}

// ---------- Router ----------
$method = $_SERVER["REQUEST_METHOD"] ?? 'GET';

// GET ?tipo=carreras
if ($method === "GET" && isset($_GET["tipo"]) && $_GET["tipo"] === "carreras") {
  try {
    $res = $mysqli->query("SELECT id_Carrera, nombre FROM Carreras ORDER BY nombre");
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    json_ok($rows);
  } catch (Throwable $e) {
    json_err("No se pudieron cargar carreras.", 500);
  }
}

// Resto de métodos
try {
  switch ($method) {

    // -------- LISTAR materias --------
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

    // -------- CREAR materia (activa por defecto) --------
    case "POST": {
      $in         = read_json_body();
      $nombre     = trim($in["nombre"]     ?? "");
      $id_Carrera = (int)($in["id_Carrera"] ?? 0);

      if ($nombre === "" || $id_Carrera <= 0) {
        json_err("Nombre e id_Carrera son obligatorios.", 400);
      }

      // ¡IMPORTANTE! No enviar nombre_norm (columna generada)
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
        // 1062 = duplicado (índice único sobre nombre_norm + id_Carrera)
        if ($e->getCode() === 1062) {
          json_err("❗ Esa materia ya existe en esa carrera.", 409);
        }
        json_err("No se pudo guardar la materia.", 500);
      }
    }

    // -------- ACTUALIZAR (toggle ó editar) --------
    case "PUT": {
      $in         = read_json_body();
      $id_Materia = (int)($in["id_Materia"] ?? 0);
      if ($id_Materia <= 0) json_err("ID de materia inválido.", 400);

      // A) Toggle activo/inactivo: {id_Materia, set_activo: 0|1}
      if (array_key_exists("set_activo", $in)) {
        $set = (int)$in["set_activo"]; // 1 = activo, 0 = inactivo
        if ($set !== 0 && $set !== 1) json_err("Parámetro set_activo debe ser 0 o 1.", 400);
        $nuevoEstado = ($set === 1) ? 1 : 2; // 1=Activo, 2=Inactivo

        $stmt = $mysqli->prepare("UPDATE Materias SET id_Estado = ? WHERE id_Materia = ?");
        $stmt->bind_param("ii", $nuevoEstado, $id_Materia);
        $stmt->execute();

        json_ok([
          "mensaje"   => ($set === 1 ? "Materia activada" : "Materia inactivada"),
          "id_Estado" => $nuevoEstado
        ]);
      }

      // B) Editar nombre/carrera: {id_Materia, nombre, id_Carrera}
      $nombre     = trim($in["nombre"]     ?? "");
      $id_Carrera = (int)($in["id_Carrera"] ?? 0);
      if ($nombre === "" || $id_Carrera <= 0) {
        json_err("Datos incompletos para actualizar.", 400);
      }

      // ¡IMPORTANTE! No actualizar nombre_norm (generada)
      $stmt = $mysqli->prepare(
        "UPDATE Materias SET nombre = ?, id_Carrera = ? WHERE id_Materia = ?"
      );
      $stmt->bind_param("sii", $nombre, $id_Carrera, $id_Materia);

      try {
        $stmt->execute();
        json_ok(["mensaje" => "✏️ Materia actualizada"]);
      } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) { // duplicado por índice único
          json_err("❗ Ese nombre ya existe en esa carrera.", 409);
        }
        json_err("No se pudo actualizar la materia.", 500);
      }
    }

    // -------- DELETE físico (opcional) --------
    case "DELETE": {
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
