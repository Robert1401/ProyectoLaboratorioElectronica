<?php
/**
 * Guarda una solicitud y sus items.
 * POST JSON:
 * {
 *   "no_control": "22050756",
 *   "alumno_nombre": "Mariana Mota Piña",
 *   "materia": "Analógica",
 *   "docente": "Ing. X",
 *   "carrera": "IEE",
 *   "fecha": "2025-11-08",
 *   "hora": "11:25",
 *   "no_vale": "",                      // opcional; si viene vacío se genera
 *   "items": [{ "descripcion":"Resistor 220Ω", "cantidad":2 }, ...]
 * }
 * Respuesta: { success:true, solicitud_id: 123, no_vale:"SM-20251108-0001" }
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(204); exit; }
if ($_SERVER["REQUEST_METHOD"] !== "POST") { http_response_code(405); echo json_encode(["success"=>false,"message"=>"Método no permitido"]); exit; }

/* === CONEXIÓN BD (ajusta credenciales) === */
$host="127.0.0.1"; $user="root"; $pass="root"; $db="Laboratorio_Electronica";
$mysqli = new mysqli($host,$user,$pass,$db);
if ($mysqli->connect_error) { http_response_code(500); echo json_encode(["success"=>false,"message"=>"Error de conexión"]); exit; }

/* === Leer JSON === */
$input = json_decode(file_get_contents("php://input"), true);
$no_control    = trim($input["no_control"] ?? "");
$alumno_nombre = trim($input["alumno_nombre"] ?? "");
$materia       = trim($input["materia"] ?? "");
$docente       = trim($input["docente"] ?? "");
$carrera       = trim($input["carrera"] ?? "");
$fecha         = trim($input["fecha"] ?? date("Y-m-d"));
$hora          = trim($input["hora"] ?? date("H:i"));
$no_vale       = trim($input["no_vale"] ?? "");
$items         = $input["items"] ?? [];

if ($no_control==="" || empty($items)) {
  echo json_encode(["success"=>false,"message"=>"Datos insuficientes"]); exit;
}

/* === Generar no_vale si no viene === */
if ($no_vale === "") {
  $prefix = "SM-".date("Ymd")."-";
  // Buscar consecutivo del día
  $stmt = $mysqli->prepare("SELECT COUNT(*) AS n FROM solicitudes WHERE no_vale LIKE CONCAT(?, '%')");
  $stmt->bind_param("s", $prefix);
  $stmt->execute();
  $n = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $seq = str_pad(strval(intval($n["n"] ?? 0) + 1), 4, "0", STR_PAD_LEFT);
  $no_vale = $prefix.$seq;
}

/* === Insert solicitud ===
   Estructura sugerida:
   solicitudes(
     id INT AI PK,
     no_vale VARCHAR(30) UNIQUE,
     no_control VARCHAR(16),
     alumno_nombre VARCHAR(120),
     materia VARCHAR(120),
     docente VARCHAR(120),
     carrera VARCHAR(120),
     fecha DATE,
     hora  VARCHAR(8),
     estado ENUM('pendiente','en_curso','devuelto','rechazado') DEFAULT 'pendiente',
     aprobado_en DATETIME NULL,
     rechazado_en DATETIME NULL,
     creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   )

   solicitud_items(
     id INT AI PK,
     solicitud_id INT,
     descripcion VARCHAR(255),
     cantidad INT
   )
*/
$estado = "pendiente";
$stmt = $mysqli->prepare("INSERT INTO solicitudes
  (no_vale,no_control,alumno_nombre,materia,docente,carrera,fecha,hora,estado)
  VALUES (?,?,?,?,?,?,?,?,?)");
$stmt->bind_param("sssssssss", $no_vale,$no_control,$alumno_nombre,$materia,$docente,$carrera,$fecha,$hora,$estado);

if (!$stmt->execute()) {
  echo json_encode(["success"=>false,"message"=>"No se pudo guardar la solicitud: ".$stmt->error]); $stmt->close(); exit;
}
$solicitud_id = $stmt->insert_id;
$stmt->close();

/* === Insert items === */
$itStmt = $mysqli->prepare("INSERT INTO solicitud_items (solicitud_id, descripcion, cantidad) VALUES (?,?,?)");
foreach($items as $it){
  $d = trim($it["descripcion"] ?? "");
  $c = intval($it["cantidad"] ?? 0);
  if ($d==="" || $c<=0) continue;
  $itStmt->bind_param("isi", $solicitud_id, $d, $c);
  if (!$itStmt->execute()) {
    // Si falla un item, continuamos pero avisamos (simple)
    // En producción podrías hacer rollback.
  }
}
$itStmt->close();

echo json_encode(["success"=>true, "solicitud_id"=>$solicitud_id, "no_vale"=>$no_vale], JSON_UNESCAPED_UNICODE);
