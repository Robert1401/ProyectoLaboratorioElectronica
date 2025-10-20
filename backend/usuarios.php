<?php
/* =========================================
   API Personas (Alumnos / Auxiliares / Docentes)
   En línea con tu HTML/CSS/JS
========================================= */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit(); }

/* === CONEXIÓN === */
$host     = "127.0.0.1";   // o "localhost"
$username = "root";
$password = "root";        // <-- coloca tu contraseña real
$database = "Laboratorio_Electronica";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $conn = new mysqli($host, $username, $password, $database);
  $conn->set_charset("utf8mb4");
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["error" => "Error de conexión: ".$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit();
}

/* === HELPERS === */
function out($data, $code=200){
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit();
}
function body_json(){
  $raw = file_get_contents("php://input");
  if ($raw === false || $raw === "") return [];
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}
function parse_body_kv(){
  $raw = file_get_contents("php://input"); $out=[];
  parse_str($raw, $out);
  return $out;
}

/* =========================================
   RUTAS
========================================= */
$method = $_SERVER["REQUEST_METHOD"];

/* =========== GET =========== */
if ($method === "GET") {

  // Catálogo de carreras
  if (isset($_GET["carreras"])) {
    $sql = "SELECT id_Carrera, nombre FROM Carreras ORDER BY nombre ASC";
    $rows = [];
    $res = $conn->query($sql);
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    out($rows);
  }

  // Listado general: alumnos + auxiliares + docentes
  if (isset($_GET["personas"])) {
    $todo = [];

    // Alumnos
    $resA = $conn->query("
      SELECT 'Alumno' AS rol,
             a.numeroControl  AS numero,
             a.nombre, a.apellidoPaterno, a.apellidoMaterno
      FROM Alumnos a
      ORDER BY a.numeroControl ASC
    ");
    while ($r = $resA->fetch_assoc()) $todo[] = $r;

    // Auxiliares
    $resX = $conn->query("
      SELECT 'Auxiliar' AS rol,
             x.numeroTrabajador AS numero,
             x.nombre, x.apellidoPaterno, x.apellidoMaterno
      FROM Auxiliares x
      ORDER BY x.numeroTrabajador ASC
    ");
    while ($r = $resX->fetch_assoc()) $todo[] = $r;

    // Docentes (ajusta nombre de tabla/campos si los tuyos difieren)
    $resD = $conn->query("
      SELECT 'Docente' AS rol,
             d.numeroTrabajador AS numero,
             d.nombre, d.apellidoPaterno, d.apellidoMaterno
      FROM Docentes d
      ORDER BY d.numeroTrabajador ASC
    ");
    while ($r = $resD->fetch_assoc()) $todo[] = $r;

    out($todo);
  }

  // Buscar por número (control o trabajador)
  if (isset($_GET["num"])) {
    $buscar = trim($_GET["num"]);

    // 1) Alumno
    $stA = $conn->prepare("
      SELECT 'Alumno' AS rol,
             a.numeroControl, a.nombre, a.apellidoPaterno, a.apellidoMaterno,
             c.id_Carrera, c.nombre AS carrera
      FROM Alumnos a
      LEFT JOIN Carreras c ON c.id_Carrera = a.id_Carrera
      WHERE a.numeroControl = ?
      LIMIT 1
    ");
    $stA->bind_param("s", $buscar);
    $stA->execute();
    $rA = $stA->get_result()->fetch_assoc();
    if ($rA) out($rA);

    // 2) Auxiliar
    $stX = $conn->prepare("
      SELECT 'Auxiliar' AS rol,
             x.numeroTrabajador, x.nombre, x.apellidoPaterno, x.apellidoMaterno
      FROM Auxiliares x
      WHERE x.numeroTrabajador = ?
      LIMIT 1
    ");
    $stX->bind_param("s", $buscar);
    $stX->execute();
    $rX = $stX->get_result()->fetch_assoc();
    if ($rX) out($rX);

    // 3) Docente
    $stD = $conn->prepare("
      SELECT 'Docente' AS rol,
             d.numeroTrabajador, d.nombre, d.apellidoPaterno, d.apellidoMaterno
      FROM Docentes d
      WHERE d.numeroTrabajador = ?
      LIMIT 1
    ");
    $stD->bind_param("s", $buscar);
    $stD->execute();
    $rD = $stD->get_result()->fetch_assoc();
    if ($rD) out($rD);

    out(["error" => "No se encontró el número especificado"], 404);
  }

  out(["error" => "Ruta GET no válida"], 400);
}

/* =========== POST (Crear) =========== */
if ($method === "POST") {
  $d = body_json();
  $rol = trim($d["rol"] ?? "");

  if ($rol === "Alumno") {
    $nombre = trim($d["nombre"] ?? "");
    $apPat  = trim($d["apellidoPaterno"] ?? "");
    $apMat  = trim($d["apellidoMaterno"] ?? "");
    $idCarr = intval($d["id_Carrera"] ?? 0);
    $numC   = trim((string)($d["numeroControl"] ?? ""));

    if (!$nombre || !$apPat || !$apMat || !$idCarr) {
      out(["error" => "Faltan campos: nombre, apellidos, id_Carrera"], 400);
    }

    // Validar duplicado de número (si viene)
    if ($numC !== "") {
      $chk = $conn->prepare("SELECT 1 FROM Alumnos WHERE numeroControl=? LIMIT 1");
      $chk->bind_param("s", $numC); $chk->execute();
      if ($chk->get_result()->fetch_row()) out(["error"=>"El número de control ya existe"], 409);
    }

    $idEstado = 1;
    if ($numC !== "") {
      $st = $conn->prepare("INSERT INTO Alumnos(numeroControl,nombre,apellidoPaterno,apellidoMaterno,id_Estado,id_Carrera)
                            VALUES(?, ?, ?, ?, ?, ?)");
      $st->bind_param("isssii", $numC, $nombre, $apPat, $apMat, $idEstado, $idCarr);
      $st->execute();
      out(["mensaje"=>"Alumno registrado","numeroControl"=>$numC], 201);
    } else {
      $st = $conn->prepare("INSERT INTO Alumnos(nombre,apellidoPaterno,apellidoMaterno,id_Estado,id_Carrera)
                            VALUES(?, ?, ?, ?, ?)");
      $st->bind_param("sssii", $nombre, $apPat, $apMat, $idEstado, $idCarr);
      $st->execute();
      $nuevo = (string)$conn->insert_id;
      out(["mensaje"=>"Alumno registrado","numeroControl"=>$nuevo], 201);
    }
  }

  if ($rol === "Auxiliar") {
    $nombre = trim($d["nombre"] ?? "");
    $apPat  = trim($d["apellidoPaterno"] ?? "");
    $apMat  = trim($d["apellidoMaterno"] ?? "");
    $numT   = trim((string)($d["numeroTrabajador"] ?? ""));

    if (!$nombre || !$apPat || !$apMat) {
      out(["error" => "Faltan campos: nombre y apellidos"], 400);
    }

    // Validar duplicado si viene número
    if ($numT !== "") {
      $chk = $conn->prepare("SELECT 1 FROM Auxiliares WHERE numeroTrabajador=? LIMIT 1");
      $chk->bind_param("s", $numT); $chk->execute();
      if ($chk->get_result()->fetch_row()) out(["error"=>"El número de trabajador ya existe"], 409);
    }

    $idEstado = 1;
    if ($numT !== "") {
      $st = $conn->prepare("INSERT INTO Auxiliares(numeroTrabajador,id_Estado,nombre,apellidoPaterno,apellidoMaterno)
                            VALUES(?, ?, ?, ?, ?)");
      $st->bind_param("sisss", $numT, $idEstado, $nombre, $apPat, $apMat);
      $st->execute();
      out(["mensaje"=>"Auxiliar registrado","numeroTrabajador"=>$numT], 201);
    } else {
      $st = $conn->prepare("INSERT INTO Auxiliares(id_Estado,nombre,apellidoPaterno,apellidoMaterno)
                            VALUES(?, ?, ?, ?)");
      $st->bind_param("isss", $idEstado, $nombre, $apPat, $apMat);
      $st->execute();
      $nuevo = (string)$conn->insert_id;
      out(["mensaje"=>"Auxiliar registrado","numeroTrabajador"=>$nuevo], 201);
    }
  }

  // Si más adelante quieres crear Docentes desde la UI,
  // aquí podrías añadir el bloque POST para 'Docente'.

  out(["error"=>"Rol inválido. Usa Alumno | Auxiliar"], 400);
}

/* =========== PUT (Actualizar) =========== */
if ($method === "PUT") {
  $d = body_json();
  $rol = trim($d["rol"] ?? "");

  if ($rol === "Alumno") {
    $ref = trim((string)($d["numeroControl"] ?? ""));
    if ($ref === "") out(["error"=>"Falta 'numeroControl' de referencia"], 400);

    $campos = []; $types=""; $vals=[];
    foreach (["nombre","apellidoPaterno","apellidoMaterno"] as $c) {
      if (array_key_exists($c,$d)) { $campos[]="$c=?"; $types.="s"; $vals[] = trim((string)$d[$c]); }
    }
    if (array_key_exists("id_Carrera",$d)) { $campos[]="id_Carrera=?"; $types.="i"; $vals[] = intval($d["id_Carrera"]); }

    if ($campos) {
      $sql = "UPDATE Alumnos SET ".implode(", ",$campos)." WHERE numeroControl=?";
      $types .= "s"; $vals[] = $ref;
      $st=$conn->prepare($sql);
      $st->bind_param($types, ...$vals);
      $st->execute();
    }

    if (isset($d["numeroControlNuevo"])) {
      $nuevo = trim((string)$d["numeroControlNuevo"]);
      if ($nuevo !== "" && $nuevo !== $ref) {
        $chk = $conn->prepare("SELECT 1 FROM Alumnos WHERE numeroControl=? LIMIT 1");
        $chk->bind_param("s", $nuevo); $chk->execute();
        if ($chk->get_result()->fetch_row()) out(["error"=>"El número de control nuevo ya existe"], 409);

        $u = $conn->prepare("UPDATE Alumnos SET numeroControl=? WHERE numeroControl=?");
        $u->bind_param("ss", $nuevo, $ref);
        $u->execute();
      }
    }
    out(["mensaje"=>"Alumno actualizado"]);
  }

  if ($rol === "Auxiliar") {
    $ref = trim((string)($d["numeroTrabajador"] ?? ""));
    if ($ref === "") out(["error"=>"Falta 'numeroTrabajador' de referencia"], 400);

    $campos = []; $types=""; $vals=[];
    foreach (["nombre","apellidoPaterno","apellidoMaterno"] as $c) {
      if (array_key_exists($c,$d)) { $campos[]="$c=?"; $types.="s"; $vals[] = trim((string)$d[$c]); }
    }
    if ($campos) {
      $sql = "UPDATE Auxiliares SET ".implode(", ",$campos)." WHERE numeroTrabajador=?";
      $types .= "s"; $vals[] = $ref;
      $st=$conn->prepare($sql);
      $st->bind_param($types, ...$vals);
      $st->execute();
    }

    if (isset($d["numeroTrabajadorNuevo"])) {
      $nuevo = trim((string)$d["numeroTrabajadorNuevo"]);
      if ($nuevo !== "" && $nuevo !== $ref) {
        $chk = $conn->prepare("SELECT 1 FROM Auxiliares WHERE numeroTrabajador=? LIMIT 1");
        $chk->bind_param("s", $nuevo); $chk->execute();
        if ($chk->get_result()->fetch_row()) out(["error"=>"El número de trabajador nuevo ya existe"], 409);

        $u = $conn->prepare("UPDATE Auxiliares SET numeroTrabajador=? WHERE numeroTrabajador=?");
        $u->bind_param("ss", $nuevo, $ref);
        $u->execute();
      }
    }
    out(["mensaje"=>"Auxiliar actualizado"]);
  }

  // Si más adelante quieres editar Docentes, aquí puedes agregar el bloque PUT 'Docente'.

  out(["error"=>"Rol inválido. Usa Alumno | Auxiliar"], 400);
}

/* =========== DELETE (Eliminar) =========== */
if ($method === "DELETE") {
  $kv = parse_body_kv();
  $numC = isset($kv["numeroControl"]) ? trim((string)$kv["numeroControl"]) : "";
  $numT = isset($kv["numeroTrabajador"]) ? trim((string)$kv["numeroTrabajador"]) : "";

  if ($numC !== "") {
    $st = $conn->prepare("DELETE FROM Alumnos WHERE numeroControl=?");
    $st->bind_param("s", $numC);
    $st->execute();
    if ($st->affected_rows === 0) out(["error"=>"Alumno no encontrado"], 404);
    out(["mensaje"=>"Alumno eliminado"]);
  }

  if ($numT !== "") {
    // El mismo endpoint te sirve para eliminar Auxiliar o Docente si compartes clave 'numeroTrabajador'
    // (si quieres separar Docentes, crea otro branch similar apuntando a su tabla).
    $st = $conn->prepare("DELETE FROM Auxiliares WHERE numeroTrabajador=?");
    $st->bind_param("s", $numT);
    $st->execute();
    if ($st->affected_rows === 0) {
      // Intento en docentes si no estaba en auxiliares
      $st2 = $conn->prepare("DELETE FROM Docentes WHERE numeroTrabajador=?");
      $st2->bind_param("s", $numT);
      $st2->execute();
      if ($st2->affected_rows === 0) out(["error"=>"Registro no encontrado"], 404);
    }
    out(["mensaje"=>"Registro eliminado"]);
  }

  out(["error"=>"Falta numeroControl o numeroTrabajador"], 400);
}

/* Ruta no reconocida */
out(["error"=>"Ruta no válida"], 400);
