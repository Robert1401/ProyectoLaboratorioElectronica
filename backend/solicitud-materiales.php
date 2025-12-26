<?php
// backend/solicitud-materiales.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// =================== CONFIG BD (AJUSTA ESTO) ===================
$DB_HOST = '127.0.0.1';
$DB_NAME = 'laboratorio_electronica';
$DB_USER = 'root';
$DB_PASS = 'root';
$DSN = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";

function pdo(): PDO {
  global $DSN, $DB_USER, $DB_PASS;
  static $pdo = null;
  if ($pdo === null) {
    $pdo = new PDO($DSN, $DB_USER, $DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  }
  return $pdo;
}
function json_ok($arr = []) { echo json_encode(['ok'=>true] + $arr, JSON_UNESCAPED_UNICODE); exit; }
function json_err($msg, $code=400) { http_response_code($code); echo json_encode(['ok'=>false,'message'=>$msg], JSON_UNESCAPED_UNICODE); exit; }

// =================== RUTAS ===================
$method = $_SERVER['REQUEST_METHOD'];
$view   = $_GET['view'] ?? '';

try {
  if ($method === 'GET') {
    // 1) Materiales (ajusta el nombre de la tabla si no es "Materias")
    if ($view === 'materiales') {
      // Esperado: id_Material, nombre, cantidad
      $sql = "SELECT id_Material, nombre, cantidad FROM Materias ORDER BY nombre";
      $rows = pdo()->query($sql)->fetchAll();
      json_ok(['materiales' => $rows]);
    }

    // 2) Alumno + Carrera por número de control (nc)
    if ($view === 'alumno') {
      $nc = $_GET['nc'] ?? '';
      if ($nc === '') json_err('Falta parámetro nc');

      // Basado en tus capturas:
      //  - Personas: numeroControl, nombre, apellidoPaterno, apellidoMaterno
      //  - Usuarios: numeroControl (relación con Personas)
      //  - CarrerasAlumnos: numeroControl, id_Carrera
      //  - Carreras: id_Carrera, nombre
      $sql = "
        SELECT 
          u.numeroControl,
          CONCAT(p.nombre,' ',p.apellidoPaterno,' ',p.apellidoMaterno) AS nombre,
          c.nombre AS carrera
        FROM Usuarios u
        INNER JOIN Personas p ON p.numeroControl = u.numeroControl
        LEFT JOIN CarrerasAlumnos ca ON ca.numeroControl = u.numeroControl
        LEFT JOIN Carreras c ON c.id_Carrera = ca.id_Carrera
        WHERE u.numeroControl = ?
        LIMIT 1
      ";
      $st = pdo()->prepare($sql);
      $st->execute([$nc]);
      $row = $st->fetch();

      if (!$row) json_err('No existe el número de control', 404);
      json_ok(['alumno' => $row]);
    }

    json_err('Ruta GET no válida', 404);
  }

  if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) json_err('JSON inválido');
    $tipo = $data['tipo'] ?? '';

    if ($tipo === 'solicitud_materiales') {
      // Campos esperados desde tu front:
      // folio, fechaISO, hora, auxiliar, docente, carrera, alumno, materia, items[{id_Material,cantidad}]
      // Si ya tienes tablas, descomenta y ajusta INSERTS.
      /*
      $pdo = pdo();
      $pdo->beginTransaction();

      $sqlHead = "INSERT INTO Solicitudes (folio, fecha, hora, auxiliar, docente, carrera, alumno, materia)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
      $pdo->prepare($sqlHead)->execute([
        $data['folio'] ?? null,
        $data['fechaISO'] ?? null,
        $data['hora'] ?? null,
        $data['auxiliar'] ?? null,
        $data['docente'] ?? null,
        $data['carrera'] ?? null,
        $data['alumno'] ?? null,
        $data['materia'] ?? null
      ]);
      $idSolicitud = (int)$pdo->lastInsertId();

      if (!empty($data['items']) && is_array($data['items'])) {
        $sqlDet = "INSERT INTO SolicitudesMateriales (id_solicitud, id_Material, cantidad) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sqlDet);
        foreach ($data['items'] as $it) {
          $stmt->execute([$idSolicitud, $it['id_Material'], $it['cantidad']]);
        }
      }

      $pdo->commit();
      */

      // Respuesta mínima para no romper flujo si aún no insertas
      json_ok(['message'=>'Solicitud recibida', 'echo'=>$data]);
    }

    json_err('Ruta POST no válida', 404);
  }

  json_err('Método no permitido', 405);
} catch (Throwable $e) {
  json_err('Error: '.$e->getMessage(), 500);
}
