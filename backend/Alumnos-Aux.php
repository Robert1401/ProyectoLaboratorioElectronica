<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit(); }

/* === CONEXIÓN === */
$servername = "127.0.0.1";   // o "localhost"
$username   = "root";
$password   = "root";        // <- ajusta si es distinto
$database   = "Laboratorio_Electronica";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(["error" => "Error de conexión a BD: ".$conn->connect_error], JSON_UNESCAPED_UNICODE);
  exit();
}

/* Helpers */
function out($arr, $code=200){
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit();
}
function table_exists(mysqli $conn, string $table): bool {
  $stmt = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
  $stmt->bind_param("s", $table);
  $stmt->execute();
  $ok = (bool)$stmt->get_result()->fetch_row();
  $stmt->close();
  return $ok;
}
function column_exists(mysqli $conn, string $table, string $column): bool {
  $stmt = $conn->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
  $stmt->bind_param("ss", $table, $column);
  $stmt->execute();
  $ok = (bool)$stmt->get_result()->fetch_row();
  $stmt->close();
  return $ok;
}

/* ===================== GET lista =======================
   - GET /Alumnos-Aux.php
   - GET /Alumnos-Aux.php?q=texto      (filtra por no. control, carrera o materia)
   - GET /Alumnos-Aux.php?limit=100
   - GET /Alumnos-Aux.php?offset=0
   Devuelve:
   [
     {"numeroControl":"2145...", "carrera":"Sistemas", "materia":"Función Electromagnética, Fundamentos..."},
     ...
   ]
========================================================= */
if ($_SERVER["REQUEST_METHOD"] === "GET") {

  $q       = isset($_GET["q"]) ? trim($_GET["q"]) : "";
  $limit   = isset($_GET["limit"]) ? max(1, intval($_GET["limit"])) : 500;
  $offset  = isset($_GET["offset"]) ? max(0, intval($_GET["offset"])) : 0;

  $hasMaterias          = table_exists($conn, "Materias");
  $materiasTieneCarrera = $hasMaterias && column_exists($conn, "Materias", "id_Carrera");

  // SELECT base
  $select = "
    SELECT 
      a.numeroControl,
      COALESCE(c.nombre, '') AS carrera
  ";

  // Si Materias.id_Carrera existe, listamos materias de la carrera del alumno
  if ($materiasTieneCarrera) {
    $select .= ",
      COALESCE(m.materiaListado, '') AS materia
    ";
    $from = "
      FROM Alumnos a
      LEFT JOIN Carreras c ON c.id_Carrera = a.id_Carrera
      LEFT JOIN (
        SELECT id_Carrera, GROUP_CONCAT(DISTINCT nombre ORDER BY nombre SEPARATOR ', ') AS materiaListado
        FROM Materias
        GROUP BY id_Carrera
      ) m ON m.id_Carrera = a.id_Carrera
    ";
  } else {
    // fallback sin materias
    $select .= ",
      '' AS materia
    ";
    $from = "
      FROM Alumnos a
      LEFT JOIN Carreras c ON c.id_Carrera = a.id_Carrera
    ";
  }

  // WHERE (filtro)
  $where = "";
  $types = "";
  $params = [];

  if ($q !== "") {
    $where = " WHERE (a.numeroControl LIKE CONCAT('%',?,'%') OR c.nombre LIKE CONCAT('%',?,'%')";
    $types .= "ss";
    $params[] = $q;
    $params[] = $q;

    if ($materiasTieneCarrera) {
      // buscar también por nombres de materias de la misma carrera
      $where .= " OR EXISTS(
                    SELECT 1
                    FROM Materias m2
                    WHERE m2.id_Carrera = a.id_Carrera
                      AND m2.nombre LIKE CONCAT('%',?,'%')
                  )";
      $types .= "s";
      $params[] = $q;
    }

    $where .= ")";
  }

  $order    = " ORDER BY a.numeroControl ASC ";
  $limitSql = " LIMIT ? OFFSET ? ";
  $types   .= "ii";
  $params[] = $limit;
  $params[] = $offset;

  $sql = $select . $from . $where . $order . $limitSql;

  $stmt = $conn->prepare($sql);
  if (!$stmt) out(["error" => "Error al preparar SQL: ".$conn->error], 500);

  if (!$stmt->bind_param($types, ...$params)) {
    out(["error" => "Error al enlazar parámetros: ".$stmt->error], 500);
  }
  if (!$stmt->execute()) {
    out(["error" => "Error al ejecutar consulta: ".$stmt->error], 500);
  }

  $res = $stmt->get_result();
  $rows = [];
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  $stmt->close();

  out($rows);
}

out(["error" => "Ruta no válida"], 400);
