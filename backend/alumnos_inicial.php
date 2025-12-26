<?php
/**
 * API – Alumnos inicial (estado y notificaciones)
 * ------------------------------------------------
 * GET /api/alumnos_inicial.php?action=estado&no_control=20181234
 * GET /api/alumnos_inicial.php?action=notificaciones&no_control=20181234
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
  http_response_code(200); exit();
}

/* ========== CONFIG BD (AJUSTA) ========== */
$servername = "127.0.0.1";     // o "localhost"
$username   = "root";
$password   = "root";          // tu password
$database   = "Laboratorio_Electronica";

/* Cooldown (minutos) para volver a pedir material después de aprobar */
define('COOLDOWN_MINUTES', 120);

/* ========== CONEXIÓN ========== */
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(["ok"=>false, "msg"=>"Error de conexión: ".$conn->connect_error], JSON_UNESCAPED_UNICODE);
  exit();
}

/* Helpers */
function out($arr, $code=200){
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit();
}
function table_exists(mysqli $conn, string $table): bool {
  $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param("s", $table);
  $st->execute();
  $ok = (bool) $st->get_result()->fetch_row();
  $st->close();
  return $ok;
}

/* ========== VALIDACIÓN TABLAS ========== */
$hasSolicitudes = table_exists($conn, "solicitudes");
$hasItems       = table_exists($conn, "solicitud_items");
$hasNotifs      = table_exists($conn, "notificaciones");

if (!$hasSolicitudes) {
  out(["ok"=>false,"msg"=>"La tabla 'solicitudes' no existe."], 500);
}

/* ========== PARÁMETROS ========== */
$action     = isset($_GET["action"]) ? trim($_GET["action"]) : "estado";
$no_control = isset($_GET["no_control"]) ? trim($_GET["no_control"]) : "";

/* ========== ACCIÓN: ESTADO ========== */
if ($action === "estado") {
  if ($no_control === "") out(["ok"=>false,"msg"=>"Parámetro 'no_control' es requerido."], 422);

  // Tomamos la solicitud MÁS RECIENTE del alumno
  $sql = "SELECT id, no_vale, fecha, hora, materia, maestro, mesa, no_control, estado, aprobado_en, rechazado_en, creado_en
          FROM solicitudes
          WHERE no_control = ?
          ORDER BY id DESC
          LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param("s", $no_control);
  $st->execute();
  $res = $st->get_result();
  $sol = $res->fetch_assoc();
  $st->close();

  if (!$sol) {
    // Alumno sin solicitudes → todo habilitado, sin cooldown
    out([
      "ok" => true,
      "alumno" => ["no_control" => $no_control],
      "prestamo" => null,
      "cooldown" => ["minutes" => COOLDOWN_MINUTES, "ms_restantes" => 0]
    ]);
  }

  // Contar items si existe la tabla solicitud_items
  $itemsCount = 0;
  if ($hasItems) {
    $st = $conn->prepare("SELECT COUNT(*) AS n FROM solicitud_items WHERE solicitud_id = ?");
    $st->bind_param("i", $sol["id"]);
    $st->execute();
    $n = $st->get_result()->fetch_assoc();
    $st->close();
    $itemsCount = intval($n["n"] ?? 0);
  }

  // Cooldown si estado = en_curso con aprobado_en
  $ms_restantes = 0;
  if ($sol["estado"] === "en_curso" && !empty($sol["aprobado_en"])) {
    $t0 = strtotime($sol["aprobado_en"]) * 1000; // ms
    if ($t0 > 0) {
      $cooldown_ms = COOLDOWN_MINUTES * 60 * 1000;
      $ms_restantes = max(0, ($t0 + $cooldown_ms) - round(microtime(true)*1000));
    }
  }

  out([
    "ok" => true,
    "alumno" => [
      "no_control" => $no_control
    ],
    "prestamo" => [
      "id"          => intval($sol["id"]),
      "no_vale"     => $sol["no_vale"],
      "estado"      => $sol["estado"],         // pendiente | en_curso | devuelto | rechazado | aprobado(en tu flujo lo paso a en_curso)
      "aprobado_en" => $sol["aprobado_en"],
      "fecha"       => $sol["fecha"],
      "hora"        => $sol["hora"],
      "materia"     => $sol["materia"],
      "maestro"     => $sol["maestro"],
      "mesa"        => $sol["mesa"],
      "items_count" => $itemsCount
    ],
    "cooldown" => [
      "minutes"       => COOLDOWN_MINUTES,
      "ms_restantes"  => $ms_restantes
    ]
  ]);
}

/* ========== ACCIÓN: NOTIFICACIONES ========== */
if ($action === "notificaciones") {
  if (!$hasNotifs) out(["ok"=>true,"rows"=>[]]); // si no existe la tabla devolvemos vacío

  if ($no_control === "") out(["ok"=>false,"msg"=>"Parámetro 'no_control' es requerido."], 422);

  $limit  = isset($_GET["limit"]) ? max(1, intval($_GET["limit"])) : 50;
  $offset = isset($_GET["offset"]) ? max(0, intval($_GET["offset"])) : 0;

  $sql = "SELECT id, mensaje, created_at, leido
          FROM notificaciones
          WHERE no_control = ?
          ORDER BY id DESC
          LIMIT ? OFFSET ?";
  $st = $conn->prepare($sql);
  $st->bind_param("sii", $no_control, $limit, $offset);
  $st->execute();
  $res = $st->get_result();

  $rows = [];
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  $st->close();

  out(["ok"=>true, "rows"=>$rows]);
}

/* ========== ACCIÓN NO VÁLIDA ========== */
out(["ok"=>false,"msg"=>"Acción no válida."], 400);
