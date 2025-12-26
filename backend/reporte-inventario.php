<?php
/******************************************************
 * Reporte de Inventario (API JSON)
 *  - GET /backend/reporte-inventario.php
 *  - GET /backend/reporte-inventario.php?auxiliares=1
 *  - Filtros: ?estado=disponible|dañado  &auxiliarId=##  &fecha=YYYY-MM-DD
 ******************************************************/
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(204); exit; }

$DB = [
  "host" => "127.0.0.1",
  "user" => "root",
  "pass" => "root",               // <- ajusta si es necesario
  "name" => "Laboratorio_Electronica"
];

function ok($data){ echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function err($msg,$code=400){ http_response_code($code); echo json_encode(["error"=>$msg], JSON_UNESCAPED_UNICODE); exit; }

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try{
  $cx = new mysqli($DB["host"], $DB["user"], $DB["pass"], $DB["name"]);
  $cx->set_charset("utf8mb4");
}catch(Throwable $e){ err("No se pudo conectar a MySQL", 500); }

/* --------- Helpers dinamicos (descubre tablas/columnas) ---------- */
function table_exists(mysqli $cx, $table){
  $res = $cx->query("SHOW TABLES LIKE '{$cx->real_escape_string($table)}'");
  return $res && $res->num_rows > 0;
}
function col_exists(mysqli $cx, $table, $col){
  $res = $cx->query("SHOW COLUMNS FROM {$table} LIKE '{$cx->real_escape_string($col)}'");
  return $res && $res->num_rows > 0;
}

/* --- Descubre tabla de auxiliares (Auxiliares o Usuarios) --- */
$AUX_TBL = null;      // nombre de la tabla
$AUX_ID  = null;      // id_Auxiliar o id_Usuario
$AUX_NOM = null;      // expresión para nombre completo

if (table_exists($cx, "Auxiliares")) {
  $AUX_TBL = "Auxiliares";
  $AUX_ID  = col_exists($cx,"Auxiliares","id_Auxiliar") ? "id_Auxiliar" : null;
  // nombre completo
  $partes = [];
  foreach (["nombre","apellidoPaterno","apellidoMaterno"] as $c) {
    if (col_exists($cx,"Auxiliares",$c)) $partes[] = $c;
  }
  $AUX_NOM = $partes ? ("TRIM(CONCAT_WS(' ', ".implode(",", $partes)."))") : null;
}
if (!$AUX_TBL && table_exists($cx, "Usuarios")) {
  $AUX_TBL = "Usuarios";
  $AUX_ID  = col_exists($cx,"Usuarios","id_Usuario") ? "id_Usuario" : null;
  $partes = [];
  foreach (["nombre","apellidoPaterno","apellidoMaterno"] as $c) {
    if (col_exists($cx,"Usuarios",$c)) $partes[] = $c;
  }
  $AUX_NOM = $partes ? ("TRIM(CONCAT_WS(' ', ".implode(",", $partes)."))") : (col_exists($cx,"Usuarios","usuario") ? "usuario" : null);
}

/* --- Lista de auxiliares para el <select> --- */
if (isset($_GET["auxiliares"])) {
  if (!$AUX_TBL || !$AUX_ID) ok([]); // no hay tabla/columna
  $sql = "SELECT {$AUX_ID} AS id, ".($AUX_NOM ?: "'Auxiliar'")." AS nombre
          FROM {$AUX_TBL}
          ORDER BY nombre";
  $rows=[]; $rs=$cx->query($sql);
  while($r=$rs->fetch_assoc()){
    $rows[]=["id"=>(int)$r["id"], "nombre"=>$r["nombre"]];
  }
  ok($rows);
}

/* --- Fuente principal: Materiales --- */
if (!table_exists($cx, "Materiales")) err("No existe la tabla Materiales", 500);

$M = "Materiales";

/* Campos de Materiales (flexibles) */
$COL_NOMBRE = col_exists($cx,$M,"nombre") ? "nombre" : (col_exists($cx,$M,"descripcion")?"descripcion":"'Material'");
$COL_CLAVE  = col_exists($cx,$M,"clave")  ? "clave"  : (col_exists($cx,$M,"codigo")?"codigo":"id_Material");
$COL_CANT   = col_exists($cx,$M,"existencia") ? "existencia" : (col_exists($cx,$M,"stock")?"stock":"cantidad");
$COL_ESTADO = col_exists($cx,$M,"estado") ? "estado" : (col_exists($cx,$M,"id_Estado")?"id_Estado":"NULL");
$COL_AUXID  = col_exists($cx,$M,"id_Auxiliar") ? "id_Auxiliar" : (col_exists($cx,$M,"id_Usuario")?"id_Usuario":"NULL");
$COL_FECHA  = col_exists($cx,$M,"fechaRegistro") ? "fechaRegistro" :
              (col_exists($cx,$M,"created_at")   ? "created_at"   :
              (col_exists($cx,$M,"updated_at")   ? "updated_at"   : "NOW()"));

/* JOIN a auxiliares si existe relación */
$JOIN = "";
$USR_EXPR = "''";
if ($AUX_TBL && $AUX_ID && $AUX_NOM && $COL_AUXID!=="NULL") {
  $JOIN = " LEFT JOIN {$AUX_TBL} a ON a.{$AUX_ID} = m.{$COL_AUXID} ";
  $USR_EXPR = "COALESCE({$AUX_NOM}, '')";
}

/* Normalización de estado */
$EST_EXPR = "LOWER(
               CASE
                 WHEN {$COL_ESTADO} IS NULL THEN 'disponible'
                 WHEN {$COL_ESTADO} IN ('disponible','dañado','danado') THEN {$COL_ESTADO}
                 WHEN {$COL_ESTADO} = 1 THEN 'disponible'
                 WHEN {$COL_ESTADO} = 2 THEN 'dañado'
                 ELSE 'disponible'
               END
             )";

/* ---------- Filtros ---------- */
$W = " WHERE 1=1 ";
$params = [];
$types  = "";

if (isset($_GET["estado"]) && $_GET["estado"]!=="") {
  $estado = strtolower(trim($_GET["estado"]));
  if ($estado==="danado") $estado="dañado";
  $W .= " AND {$EST_EXPR} = ? ";
  $params[] = $estado; $types .= "s";
}

if (isset($_GET["auxiliarId"]) && $_GET["auxiliarId"]!=="") {
  $auxId = (int)$_GET["auxiliarId"];
  if ($COL_AUXID!=="NULL") {
    $W .= " AND m.{$COL_AUXID} = ? ";
    $params[] = $auxId; $types .= "i";
  } else {
    // si no hay campo auxiliar en materiales, filtrar no es posible
    $W .= " AND 1=0 ";
  }
}

if (isset($_GET["fecha"]) && $_GET["fecha"]!=="") {
  // comparación por fecha (DATE)
  $W .= " AND DATE({$COL_FECHA}) = ? ";
  $params[] = $_GET["fecha"]; $types .= "s";
}

/* ---------- Consulta ---------- */
$sql = "SELECT
          m.{$COL_NOMBRE} AS nombre,
          m.{$COL_CLAVE}  AS clave,
          COALESCE(m.{$COL_CANT},0) AS cantidad,
          {$USR_EXPR}     AS usuario,
          {$EST_EXPR}     AS estado,
          DATE({$COL_FECHA}) AS fecha
        FROM {$M} m
        {$JOIN}
        {$W}
        ORDER BY nombre";

if ($params){
  $st=$cx->prepare($sql);
  $st->bind_param($types, ...$params);
  $st->execute();
  $rs=$st->get_result();
} else {
  $rs=$cx->query($sql);
}

$out=[];
while($r=$rs->fetch_assoc()){
  $out[]=[
    "nombre"   => $r["nombre"],
    "clave"    => $r["clave"],
    "cantidad" => (int)$r["cantidad"],
    "usuario"  => $r["usuario"],
    "estado"   => $r["estado"],
    "fecha"    => $r["fecha"]
  ];
}
ok($out);
