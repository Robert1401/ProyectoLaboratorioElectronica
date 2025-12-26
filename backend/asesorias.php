<?php
/*******************************************************
 * API: Gestión de Asesorías
 * - CRUD para docentes (AsesoriasWeb)
 * - Inscripciones de alumnos (AsesoriasInscritos)
 *******************************************************/
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(204); exit; }

/* ======= CONEXIÓN ======= */
$host = "127.0.0.1";
$user = "root";
$pass = "root";
$db   = "Laboratorio_Electronica";

function fail($msg,$code=400){
  http_response_code($code);
  echo json_encode(["ok"=>false,"error"=>$msg],JSON_UNESCAPED_UNICODE);
  exit;
}
function ok($data=null){
  echo json_encode(["ok"=>true,"data"=>$data],JSON_UNESCAPED_UNICODE);
  exit;
}
function read_json(){
  $r=file_get_contents("php://input");
  $j=$r?json_decode($r,true):[];
  if(!is_array($j)) fail("JSON inválido");
  return $j;
}
function vdate($s){ return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/',$s); }
function vhora($s){
  if(!preg_match('/^\s*(\d{2}):(\d{2})\s*-\s*(\d{2}):(\d{2})\s*$/',$s,$m)) return false;
  $a=$m[1]*60+$m[2]; $b=$m[3]*60+$m[4];
  return $b>$a;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try{
  $cx = new mysqli($host,$user,$pass,$db);
  $cx->set_charset("utf8mb4");
}catch(Throwable $e){
  fail("No se pudo conectar a MySQL: ".$e->getMessage(),500);
}

/* ======= Tablas ======= */
$cx->query("CREATE TABLE IF NOT EXISTS AsesoriasWeb(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(120) NOT NULL,
  id_profesor INT UNSIGNED NULL,
  docente_text VARCHAR(120) NULL,
  auxiliar VARCHAR(120) NULL,
  descripcion VARCHAR(600) NOT NULL,
  fecha DATE NOT NULL,
  hora VARCHAR(25) NOT NULL,
  cupo_total INT UNSIGNED NOT NULL DEFAULT 1,
  cupo_actual INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id),
  INDEX(id_profesor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$cols = [];
$res = $cx->query("SHOW COLUMNS FROM AsesoriasWeb");
while($r=$res->fetch_assoc()){ $cols[strtolower($r['Field'])]=$r; }
if (isset($cols['auxiliar']) && strtoupper($cols['auxiliar']['Null'] ?? '') === 'NO') {
  $cx->query("ALTER TABLE AsesoriasWeb MODIFY COLUMN auxiliar VARCHAR(120) NULL");
}

/* Tabla de alumnos inscritos */
$cx->query("CREATE TABLE IF NOT EXISTS AsesoriasInscritos(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_asesoria BIGINT UNSIGNED NOT NULL,
  no_control VARCHAR(30) NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  correo VARCHAR(150) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(id),
  UNIQUE KEY uniq_asesoria_alumno(id_asesoria,no_control),
  CONSTRAINT fk_ai_asesoria FOREIGN KEY (id_asesoria)
    REFERENCES AsesoriasWeb(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

/* ======= ACCIÓN (para alumno) ======= */
$action = isset($_GET["action"]) ? $_GET["action"] : null;

/* -------- Helpers cupo_actual -------- */
function recomputeCupo($cx, $id_asesoria){
  $id_asesoria = (int)$id_asesoria;
  if ($id_asesoria <= 0) return;
  // cuenta alumnos
  $st = $cx->prepare("SELECT COUNT(*) FROM AsesoriasInscritos WHERE id_asesoria=?");
  $st->bind_param("i",$id_asesoria);
  $st->execute(); $st->bind_result($c); $st->fetch(); $st->close();
  $c = (int)$c;

  $st2 = $cx->prepare("UPDATE AsesoriasWeb
                       SET cupo_actual = LEAST(cupo_total, ?)
                       WHERE id=?");
  $st2->bind_param("ii",$c,$id_asesoria);
  $st2->execute(); $st2->close();
}

/* =======================================================
 * ENDPOINTS PARA ALUMNO (inscribir, cancelar, mis, inscritos)
 * ======================================================= */

if ($action === "inscritos" && $_SERVER["REQUEST_METHOD"]==="GET") {
  $id = isset($_GET["id_asesoria"]) ? (int)$_GET["id_asesoria"] : 0;
  if ($id <= 0) fail("id_asesoria requerido.");
  $rows = [];
  $st = $cx->prepare("SELECT id_asesoria, no_control, nombre, correo
                      FROM AsesoriasInscritos
                      WHERE id_asesoria=?
                      ORDER BY created_at ASC");
  $st->bind_param("i",$id);
  $st->execute();
  $res = $st->get_result();
  while($r=$res->fetch_assoc()){ $rows[] = $r; }
  $st->close();
  ok($rows);
}

if ($action === "mis" && $_SERVER["REQUEST_METHOD"]==="GET") {
  $nc = trim($_GET["no_control"] ?? "");
  if ($nc === "") fail("no_control requerido.");
  $rows = [];
  $st = $cx->prepare("SELECT ai.id_asesoria AS id_asesoria
                      FROM AsesoriasInscritos ai
                      WHERE ai.no_control=?
                      ORDER BY ai.created_at ASC");
  $st->bind_param("s",$nc);
  $st->execute();
  $res = $st->get_result();
  while($r=$res->fetch_assoc()){ $rows[] = $r; }
  $st->close();
  ok($rows);
}

if ($action === "inscribir" && $_SERVER["REQUEST_METHOD"]==="POST") {
  $b = read_json();
  $id_asesoria = (int)($b["id_asesoria"] ?? 0);
  $no_control  = trim($b["no_control"] ?? "");
  $nombre      = trim($b["nombre"] ?? "");
  $correo      = trim($b["correo"] ?? "");

  if ($id_asesoria<=0) fail("id_asesoria requerido.");
  if ($no_control==="") fail("no_control requerido.");
  if ($nombre==="") $nombre = "Alumno";

  // verifica que exista y no esté lleno
  $st = $cx->prepare("SELECT cupo_total, cupo_actual FROM AsesoriasWeb WHERE id=?");
  $st->bind_param("i",$id_asesoria);
  $st->execute(); $st->bind_result($cupo_total,$cupo_actual);
  if (!$st->fetch()) { $st->close(); fail("Asesoría no encontrada.",404); }
  $st->close();
  if ($cupo_actual >= $cupo_total) fail("Cupo lleno.",409);

  // inserta (o ignora si ya existía)
  $st2 = $cx->prepare("INSERT INTO AsesoriasInscritos(id_asesoria,no_control,nombre,correo)
                       VALUES(?,?,?,?)
                       ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), correo=VALUES(correo)");
  $st2->bind_param("isss",$id_asesoria,$no_control,$nombre,$correo);
  $st2->execute(); $st2->close();

  recomputeCupo($cx,$id_asesoria);
  ok(["inscrito"=>true]);
}

if ($action === "cancelar" && $_SERVER["REQUEST_METHOD"]==="DELETE") {
  $id_asesoria = isset($_GET["id_asesoria"]) ? (int)$_GET["id_asesoria"] : 0;
  $no_control  = trim($_GET["no_control"] ?? "");
  if ($id_asesoria<=0) fail("id_asesoria requerido.");
  if ($no_control==="") fail("no_control requerido.");

  $st = $cx->prepare("DELETE FROM AsesoriasInscritos
                      WHERE id_asesoria=? AND no_control=?");
  $st->bind_param("is",$id_asesoria,$no_control);
  $st->execute();
  $affected = $st->affected_rows;
  $st->close();

  // Recalcula cupo aunque no haya borrado nada (por seguridad)
  recomputeCupo($cx,$id_asesoria);

  ok(["cancelado"=>$affected>0]);
}

/* Si había action pero método no coincide, ya no seguimos al CRUD */
if ($action !== null) {
  fail("Ruta / método no soportado para action=".$action,404);
}

/* =======================================================
 * A partir de aquí: CRUD DOCENTES (igual que tenías)
 * ======================================================= */

/* Lista de profesores para el <select> */
if (isset($_GET["profesores"])) {
  $out=[];
  $q="SELECT id_Profesor, nombre, apellidoPaterno, apellidoMaterno
      FROM Profesores
      ORDER BY apellidoPaterno, apellidoMaterno, nombre";
  $rs=$cx->query($q);
  while($r=$rs->fetch_assoc()){
    $out[]=[
      "id_Profesor"    => (int)$r["id_Profesor"],
      "nombreCompleto" => trim(($r["nombre"]??"")." ".($r["apellidoPaterno"]??"")." ".($r["apellidoMaterno"]??""))
    ];
  }
  ok($out);
}

/* ======= CRUD principal ======= */

/* GET: listado */
if ($_SERVER["REQUEST_METHOD"]==="GET") {
  $rows=[];
  $sql="SELECT w.*,
               COALESCE(
                 TRIM(CONCAT(p.nombre,' ',p.apellidoPaterno,' ',p.apellidoMaterno)),
                 NULLIF(w.docente_text,''),
                 NULLIF(w.auxiliar,'')
               ) AS docenteNombre
        FROM AsesoriasWeb w
        LEFT JOIN Profesores p ON p.id_Profesor = w.id_profesor
        ORDER BY w.fecha ASC, w.titulo ASC";
  $rs=$cx->query($sql);
  while($r=$rs->fetch_assoc()){
    $rows[]=[
      "id"            => (int)$r["id"],
      "titulo"        => $r["titulo"],
      "id_profesor"   => isset($r["id_profesor"]) ? ($r["id_profesor"]!==null ? (int)$r["id_profesor"] : null) : null,
      "docenteNombre" => $r["docenteNombre"] ?: "",
      "descripcion"   => $r["descripcion"],
      "fecha"         => $r["fecha"],
      "hora"          => $r["hora"],
      "cupo_total"    => (int)$r["cupo_total"],
      "cupo_actual"   => (int)$r["cupo_actual"]
    ];
  }
  ok($rows);
}

/* POST: crear asesoría */
if ($_SERVER["REQUEST_METHOD"]==="POST") {
  $b = read_json();
  $titulo      = trim($b["titulo"]??"");
  $id_prof     = isset($b["id_profesor"])? (int)$b["id_profesor"]: null;
  $descripcion = trim($b["descripcion"]??"");
  $fecha       = trim($b["fecha"]??"");
  $hora        = trim($b["hora"]??"");
  $cupo_total  = (int)($b["cupoTotal"]??$b["cupo_total"]??1);
  $cupo_actual = (int)($b["cupoActual"]??$b["cupo_actual"]??0);

  if($titulo===""||$descripcion==="") fail("Título y descripción son requeridos.");
  if(!vdate($fecha)) fail("Fecha inválida (YYYY-MM-DD).");
  if(!vhora($hora))  fail("Hora inválida (HH:MM - HH:MM).");
  if($cupo_total<1||$cupo_total>50) fail("Cupo total 1..50.");

  // nombre plano de docente
  $docente_text=null;
  if($id_prof){
    $st=$cx->prepare("SELECT TRIM(CONCAT(nombre,' ',apellidoPaterno,' ',apellidoMaterno))
                      FROM Profesores WHERE id_Profesor=?");
    $st->bind_param("i",$id_prof);
    $st->execute(); $st->bind_result($docente_text);
    $st->fetch(); $st->close();
  }

  $hasAux = isset($cols['auxiliar']);
  if ($hasAux) {
    $st=$cx->prepare("INSERT INTO AsesoriasWeb
        (titulo,id_profesor,docente_text,auxiliar,descripcion,fecha,hora,cupo_total,cupo_actual)
        VALUES(?,?,?,?,?,?,?,?,?)");
    $auxVal = $docente_text !== null ? $docente_text : "";
    $st->bind_param("sisssssii",
      $titulo,
      $id_prof,
      $docente_text,
      $auxVal,
      $descripcion,
      $fecha,
      $hora,
      $cupo_total,
      $cupo_actual
    );
  } else {
    $st=$cx->prepare("INSERT INTO AsesoriasWeb
        (titulo,id_profesor,docente_text,descripcion,fecha,hora,cupo_total,cupo_actual)
        VALUES(?,?,?,?,?,?,?,?)");
    $st->bind_param("sissssii",
      $titulo,
      $id_prof,
      $docente_text,
      $descripcion,
      $fecha,
      $hora,
      $cupo_total,
      $cupo_actual
    );
  }
  $st->execute();
  ok(["id"=>$st->insert_id]);
}

/* PUT: actualizar asesoría */
if ($_SERVER["REQUEST_METHOD"]==="PUT") {
  $b=read_json();
  $id = (int)($b["id"]??0); if($id<=0) fail("id requerido.");

  $titulo      = trim($b["titulo"]??"");
  $id_prof     = array_key_exists("id_profesor",$b)? (int)$b["id_profesor"] : null;
  $descripcion = trim($b["descripcion"]??"");
  $fecha       = trim($b["fecha"]??"");
  $hora        = trim($b["hora"]??"");
  $cupo_total  = (int)($b["cupoTotal"]??$b["cupo_total"]??1);
  $cupo_actual = (int)($b["cupoActual"]??$b["cupo_actual"]??0);

  if($titulo===""||$descripcion==="") fail("Título y descripción son requeridos.");
  if(!vdate($fecha)) fail("Fecha inválida.");
  if(!vhora($hora))  fail("Hora inválida.");
  if($cupo_total<1||$cupo_total>50) fail("Cupo total 1..50.");
  if($cupo_actual<0) $cupo_actual=0;
  if($cupo_actual>$cupo_total) $cupo_actual=$cupo_total;

  $docente_text=null;
  if($id_prof){
    $st=$cx->prepare("SELECT TRIM(CONCAT(nombre,' ',apellidoPaterno,' ',apellidoMaterno))
                      FROM Profesores WHERE id_Profesor=?");
    $st->bind_param("i",$id_prof);
    $st->execute(); $st->bind_result($docente_text);
    $st->fetch(); $st->close();
  }

  $hasAux = isset($cols['auxiliar']);
  if ($hasAux) {
    $st=$cx->prepare("UPDATE AsesoriasWeb
                      SET titulo=?, id_profesor=?, docente_text=?, auxiliar=?, descripcion=?, fecha=?, hora=?, cupo_total=?, cupo_actual=?
                      WHERE id=?");
    $auxVal = $docente_text !== null ? $docente_text : "";
    $st->bind_param("sisssssiii",
      $titulo,
      $id_prof,
      $docente_text,
      $auxVal,
      $descripcion,
      $fecha,
      $hora,
      $cupo_total,
      $cupo_actual,
      $id
    );
  } else {
    $st=$cx->prepare("UPDATE AsesoriasWeb
                      SET titulo=?, id_profesor=?, docente_text=?, descripcion=?, fecha=?, hora=?, cupo_total=?, cupo_actual=?
                      WHERE id=?");
    $st->bind_param("sissssiii",
      $titulo,
      $id_prof,
      $docente_text,
      $descripcion,
      $fecha,
      $hora,
      $cupo_total,
      $cupo_actual,
      $id
    );
  }
  $st->execute();
  ok(["updated"=>true]);
}

/* DELETE asesoría (sólo docentes, NO alumnos) */
if ($_SERVER["REQUEST_METHOD"]==="DELETE") {
  $id = isset($_GET["id"])? (int)$_GET["id"] : 0;
  if($id<=0) fail("id requerido.");
  $st=$cx->prepare("DELETE FROM AsesoriasWeb WHERE id=?");
  $st->bind_param("i",$id);
  $st->execute();
  ok(["deleted"=>true]);
}

fail("Ruta no soportada",404);
