<?php
// backend/prestamo.php
declare(strict_types=1);

// ===== CORS / JSON =====
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ===== Config BD (ajústala si difiere) =====
$DB_HOST = "127.0.0.1";
$DB_USER = "root";
$DB_PASS = "root";
$DB_NAME = "Laboratorio_Electronica";

// ===== Helpers =====
function fail(int $code, string $msg, $extra=null){
  http_response_code($code);
  echo json_encode(["ok"=>false, "error"=>$msg, "extra"=>$extra], JSON_UNESCAPED_UNICODE);
  exit;
}
function ok($data=null){
  echo json_encode(["ok"=>true, "data"=>$data], JSON_UNESCAPED_UNICODE);
  exit;
}
function get_json_body(){
  $raw = file_get_contents("php://input");
  if (!$raw) return [];
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}
function expect_str($a, $k){ return isset($a[$k]) ? trim((string)$a[$k]) : ""; }
function expect_int($a, $k){ return isset($a[$k]) && is_numeric($a[$k]) ? intval($a[$k]) : null; }
function is_yyyy_mm_dd($s){ return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $s); }
function is_hh_mm($s){ return (bool)preg_match('/^\d{2}:\d{2}$/', $s); }
function to_datetime($fecha, $hora){ return "$fecha $hora:00"; } // segundos fijos :00

// ===== Conexión =====
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $db->set_charset("utf8mb4");
} catch (Throwable $e) {
  fail(500, "No se pudo conectar a la BD", $e->getMessage());
}

// ===== Resolvedores por id / nombre =====
function find_id_materia(mysqli $db, $id_Materia, $nombre){
  if ($id_Materia !== null) return $id_Materia;
  if ($nombre === "") return null;
  $sql = "SELECT id_Materia FROM Materias WHERE nombre = ? LIMIT 1";
  $st = $db->prepare($sql); $st->bind_param("s", $nombre); $st->execute();
  $st->bind_result($id); if ($st->fetch()){ $st->close(); return (int)$id; }
  $st->close(); return null;
}
function find_id_profesor(mysqli $db, $id_Profesor, $nombre){
  if ($id_Profesor !== null) return $id_Profesor;
  if ($nombre === "") return null;
  $sql = "SELECT id_Profesor FROM Profesores WHERE CONCAT(nombre,' ',apellidoPaterno,' ',apellidoMaterno) = ? LIMIT 1";
  $st = $db->prepare($sql); $st->bind_param("s", $nombre); $st->execute();
  $st->bind_result($id); if ($st->fetch()){ $st->close(); return (int)$id; }
  $st->close(); return null;
}
function find_id_material(mysqli $db, $id_Material, $nombre){
  if ($id_Material !== null) return $id_Material;
  if ($nombre === "") return null;
  $sql = "SELECT id_Material FROM Materiales WHERE nombre = ? LIMIT 1";
  $st = $db->prepare($sql); $st->bind_param("s", $nombre); $st->execute();
  $st->bind_result($id); if ($st->fetch()){ $st->close(); return (int)$id; }
  $st->close(); return null;
}

// ===== Rutas =====
//
// POST  /backend/prestamo.php        { action: "aprobar" | "rechazar", ...payload }
// GET   /backend/prestamo.php?id=... → consulta un préstamo con sus items
//

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  // Consulta de un préstamo ya creado (con JOIN a materialesPrestados)
  $id = isset($_GET["id"]) ? intval($_GET["id"]) : 0;
  if ($id <= 0) fail(400, "Falta id");
  $sql = "SELECT p.id_Prestamo, p.numeroControl, p.id_Estado, p.id_Materia, p.id_Profesor,
                 p.fecha_Hora_Prestamo, p.mesa, p.fecha_Hora_Devolucion,
                 m.nombre AS materiaNombre,
                 pr.nombre AS profesorNombre, pr.apellidoPaterno, pr.apellidoMaterno
          FROM Prestamos p
          LEFT JOIN Materias m  ON m.id_Materia = p.id_Materia
          LEFT JOIN Profesores pr ON pr.id_Profesor = p.id_Profesor
          WHERE p.id_Prestamo = ?";
  $st = $db->prepare($sql); $st->bind_param("i", $id); $st->execute();
  $res = $st->get_result(); $row = $res->fetch_assoc(); $st->close();
  if (!$row) fail(404, "Préstamo no encontrado");

  $items = [];
  $sqlI = "SELECT mp.id_Material, mat.nombre AS materialNombre, mp.cantidad
           FROM materialesPrestados mp
           JOIN Materiales mat ON mat.id_Material = mp.id_Material
           WHERE mp.id_Prestamo = ?
           ORDER BY mat.nombre";
  $st = $db->prepare($sqlI); $st->bind_param("i", $id); $st->execute();
  $r2 = $st->get_result();
  while($it = $r2->fetch_assoc()){
    $items[] = [
      "id_Material" => (int)$it["id_Material"],
      "material"    => $it["materialNombre"],
      "cantidad"    => (int)$it["cantidad"]
    ];
  }
  $st->close();

  ok(["prestamo"=>$row, "items"=>$items]);
}

// === POST: aprobar | rechazar ===
if ($method === 'POST') {
  $j = get_json_body();
  $action = strtolower(expect_str($j, "action"));

  if ($action === "rechazar") {
    // Por ahora sólo respondemos OK (si en el futuro quieres dejar traza, aquí puedes crear una tabla Rechazos)
    ok(["status"=>"rechazado"]);
  }

  if ($action !== "aprobar") {
    fail(400, "Acción inválida. Usa 'aprobar' o 'rechazar'.");
  }

  // Payload esperado (no autogeneramos fecha/hora)
  $noVale   = expect_str($j, "noVale");              // opcional (etiqueta externa)
  $fecha    = expect_str($j, "fecha");               // OBLIGATORIA (YYYY-MM-DD)
  $hora     = expect_str($j, "hora");                // OBLIGATORIA (HH:MM)
  $mesa     = expect_int($j, "mesa");                // entero
  $noCtrl   = expect_str($j, "noControl");           // alumno
  $materiaId= expect_int($j, "id_Materia");
  $materiaNm= expect_str($j, "materia");
  $profId   = expect_int($j, "id_Profesor");
  $profNm   = expect_str($j, "profesor");
  $items    = isset($j["items"]) && is_array($j["items"]) ? $j["items"] : [];

  if ($fecha==="" || !is_yyyy_mm_dd($fecha)) fail(422, "La 'fecha' es obligatoria (YYYY-MM-DD)");
  if ($hora===""  || !is_hh_mm($hora))        fail(422, "La 'hora' es obligatoria (HH:MM)");
  if ($noCtrl==="")                            fail(422, "Falta 'noControl' del alumno");
  if ($mesa===null)                            fail(422, "Falta 'mesa'");
  if (!count($items))                          fail(422, "Debes incluir al menos un material");

  // Resolver ids por nombre si hace falta
  $idMateria  = find_id_materia($db, $materiaId, $materiaNm ?? "");
  $idProfesor = find_id_profesor($db, $profId, $profNm ?? "");
  if ($idMateria===null)  fail(422, "No se encontró la materia (por id o nombre).");
  if ($idProfesor===null) fail(422, "No se encontró el profesor (por id o nombre).");

  $fechaHoraPrestamo = to_datetime($fecha, $hora);
  $idEstado = 1; // Activo / aprobado

  try{
    $db->begin_transaction();

    // Insert en Prestamos
    $sqlP = "INSERT INTO Prestamos
              (numeroControl, id_Estado, id_Materia, id_Profesor, fecha_Hora_Prestamo, mesa, fecha_Hora_Devolucion)
             VALUES (?, ?, ?, ?, ?, ?, '0000-00-00 00:00:00')";
    $st = $db->prepare($sqlP);
    $st->bind_param("iiiiis", $noCtrl, $idEstado, $idMateria, $idProfesor, $fechaHoraPrestamo, $mesa);
    $st->execute();
    $idPrestamo = $db->insert_id;
    $st->close();

    // Insert materialesPrestados
    $sqlI = "INSERT INTO materialesPrestados (id_Estado, id_Prestamo, id_Material, cantidad)
             VALUES (?, ?, ?, ?)";
    $stI = $db->prepare($sqlI);

    foreach ($items as $it){
      $idMat = isset($it["id_Material"]) && is_numeric($it["id_Material"]) ? intval($it["id_Material"]) : null;
      $matNm = isset($it["material"]) ? trim((string)$it["material"]) : "";
      $cant  = isset($it["cantidad"]) && is_numeric($it["cantidad"]) ? intval($it["cantidad"]) : 1;

      $idMatRes = find_id_material($db, $idMat, $matNm);
      if ($idMatRes === null) {
        $db->rollback();
        fail(422, "No se encontró el material '{$matNm}' (por id o nombre).");
      }
      $stI->bind_param("iiii", $idEstado, $idPrestamo, $idMatRes, $cant);
      $stI->execute();
    }
    $stI->close();

    // (Opcional) si deseas descontar stock en Materiales, puedes hacerlo aquí.

    $db->commit();
    ok([
      "id_Prestamo" => $idPrestamo,
      "noVale"      => $noVale,
      "fecha"       => $fecha,
      "hora"        => $hora
    ]);

  }catch(Throwable $e){
    $db->rollback();
    fail(500, "No se pudo aprobar la solicitud", $e->getMessage());
  }
}

// Método no permitido
fail(405, "Método no soportado");
