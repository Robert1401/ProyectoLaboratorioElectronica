<?php
declare(strict_types=1);

/* ===== Cabeceras ===== */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

/* ===== Config DB ===== */
$host = "127.0.0.1";
$user = "root";
$pass = "root";
$db   = "Laboratorio_Electronica";

/* ===== Helpers ===== */
function fail(int $code, string $msg){
  http_response_code($code);
  echo json_encode(['ok'=>false,'error'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}
function ok($data=null){
  echo json_encode(['ok'=>true,'data'=>$data], JSON_UNESCAPED_UNICODE);
  exit;
}
function jbody(): array {
  $j = json_decode(file_get_contents('php://input') ?: '', true);
  return is_array($j) ? $j : [];
}

/**
 * Lee el "máximo por alumno" desde distintos nombres posibles:
 *  - maxAlumno
 *  - max_por_alumno
 *  - maxPorAlumno
 *  - maximo / maximo_por_alumno (por compat)
 * y lo normaliza entre 1 y 50.
 */
function read_max_alumno(array $b, ?int $default = null): ?int {
  $keys = [
    'maxAlumno',
    'max_por_alumno',
    'maxPorAlumno',
    'maximo_por_alumno',
    'maximo',
  ];
  $val = null;

  foreach ($keys as $k) {
    if (array_key_exists($k, $b)) {
      $val = (int)$b[$k];
      break;
    }
  }

  if ($val === null) {
    // si no viene, usamos default (para POST) o null (para PUT)
    return $default;
  }

  if ($val < 1)  $val = 1;
  if ($val > 50) $val = 50;
  return $val;
}

/* ===== Conexión ===== */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $cn = new mysqli($host, $user, $pass, $db);
  $cn->set_charset('utf8mb4');
} catch(Throwable $e){ fail(500, 'DB: '.$e->getMessage()); }

/* ===== util: tabla existe ===== */
function table_exists(mysqli $cn, string $db, string $table): bool {
  $st = $cn->prepare("
    SELECT 1 
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA=? AND TABLE_NAME=? 
    LIMIT 1
  ");
  $st->bind_param('ss', $db, $table); 
  $st->execute();
  $ex = (bool)$st->get_result()->fetch_row();
  $st->close();
  return $ex;
}

/* ===== Asegurar MaterialClaves con mismo tipo que Materiales.id_Material ===== */
$hasClaveTable = false;
try {
  $hasClaveTable = table_exists($cn, $db, 'MaterialClaves');

  if (!$hasClaveTable) {
    // leer tipo de id_Material
    $st = $cn->prepare("
      SELECT COLUMN_TYPE
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA=? AND TABLE_NAME='Materiales' AND COLUMN_NAME='id_Material'
      LIMIT 1
    ");
    $st->bind_param('s', $db);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$row || empty($row['COLUMN_TYPE'])) {
      $columnType = 'BIGINT(19)';
    } else {
      $columnType = strtoupper($row['COLUMN_TYPE']);
    }

    $sqlFk = "
      CREATE TABLE MaterialClaves (
        id_Material $columnType NOT NULL,
        clave       VARCHAR(10) NOT NULL,
        PRIMARY KEY (id_Material),
        UNIQUE KEY UNIQUE_CLAVE (clave),
        CONSTRAINT fk_matclave_material
          FOREIGN KEY (id_Material) REFERENCES Materiales(id_Material)
          ON DELETE CASCADE ON UPDATE CASCADE
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    try {
      $cn->query($sqlFk);
      $hasClaveTable = true;
    } catch(Throwable $e) {
      // fallback sin FK
      $sqlNoFk = "
        CREATE TABLE MaterialClaves (
          id_Material $columnType NOT NULL,
          clave       VARCHAR(10) NOT NULL,
          PRIMARY KEY (id_Material),
          UNIQUE KEY UNIQUE_CLAVE (clave)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      ";
      $cn->query($sqlNoFk);
      $hasClaveTable = true;
    }
  }
} catch(Throwable $e){
  $hasClaveTable = false;
}

/* ===== Rutas ===== */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method==='GET' && isset($_GET['ping'])) ok(['message'=>'pong']);

/* ===== GET ===== */
if ($method==='GET'){
  try{
    if ($hasClaveTable) {
      $sql = "SELECT 
                m.id_Material,
                m.id_Estado,
                m.nombre,
                m.cantidad,
                m.max_por_alumno,
                mc.clave
              FROM Materiales m
              LEFT JOIN MaterialClaves mc ON mc.id_Material = m.id_Material
              ORDER BY m.nombre";
    } else {
      $sql = "SELECT 
                m.id_Material,
                m.id_Estado,
                m.nombre,
                m.cantidad,
                m.max_por_alumno,
                NULL AS clave
              FROM Materiales m
              ORDER BY m.nombre";
    }
    $rs = $cn->query($sql);
    $out = [];
    while($r = $rs->fetch_assoc()){
      $r['id_Material']    = (int)$r['id_Material'];
      $r['id_Estado']      = (int)$r['id_Estado'];
      $r['cantidad']       = (int)$r['cantidad'];
      $r['max_por_alumno'] = isset($r['max_por_alumno']) ? (int)$r['max_por_alumno'] : 0;
      $out[] = $r;
    }
    ok($out);
  }catch(Throwable $e){ fail(500,'GET: '.$e->getMessage()); }
}

/* ===== POST (crear) ===== */
if ($method==='POST'){
  $b = jbody();
  $nombre = trim((string)($b['nombre'] ?? ''));
  $clave  = trim((string)($b['clave']  ?? ''));
  $estado = (int)($b['id_Estado'] ?? 1);
  $cant   = (int)($b['cantidad']  ?? 0);

  // ✅ CORREGIDO: ahora lee max_por_alumno / maxAlumno / etc.
  $maxAlumno = read_max_alumno($b, 1);

  if ($nombre==='') fail(400, 'El nombre es obligatorio.');
  if ($clave!=='' && strlen($clave)>10) fail(400, 'La clave máximo 10 caracteres.');

  try{
    $cn->begin_transaction();

    // único por nombre
    $st=$cn->prepare("SELECT 1 FROM Materiales WHERE nombre=?");
    $st->bind_param('s',$nombre); 
    $st->execute();
    if($st->get_result()->fetch_row()){
      $cn->rollback(); 
      fail(409,'Ya existe un material con ese nombre.');
    }
    $st->close();

    // siguiente id (no es AUTO_INCREMENT)
    $nextId = (int)($cn->query("SELECT COALESCE(MAX(id_Material),0)+1 n FROM Materiales")->fetch_assoc()['n'] ?? 1);

    // INSERT con max_por_alumno
    $st=$cn->prepare("
      INSERT INTO Materiales (id_Material, id_Estado, nombre, cantidad, max_por_alumno)
      VALUES (?,?,?,?,?)
    ");
    $st->bind_param('iisii', $nextId, $estado, $nombre, $cant, $maxAlumno);
    $st->execute(); 
    $st->close();

    // Clave (si se envió) en tabla MaterialClaves
    if ($clave!=='' && $hasClaveTable){
      $st=$cn->prepare("SELECT 1 FROM MaterialClaves WHERE clave=?");
      $st->bind_param('s',$clave); 
      $st->execute();
      if($st->get_result()->fetch_row()){
        $cn->rollback(); 
        fail(409,'La clave ya está en uso.');
      }
      $st->close();

      $st=$cn->prepare("INSERT INTO MaterialClaves (id_Material,clave) VALUES (?,?)");
      $st->bind_param('is', $nextId, $clave);
      $st->execute(); 
      $st->close();
    }

    $cn->commit();
    ok(['id_Material'=>$nextId, 'mensaje'=>'Material agregado.']);
  }catch(Throwable $e){ 
    $cn->rollback(); 
    fail(500,'POST: '.$e->getMessage()); 
  }
}

/* ===== PUT (actualizar) ===== */
if ($method==='PUT'){
  $b = jbody();
  $id     = (int)($b['id_Material'] ?? 0);
  $nombre = array_key_exists('nombre',$b) ? trim((string)$b['nombre']) : null;
  $clave  = array_key_exists('clave', $b) ? trim((string)$b['clave']) : null; // "" borra
  $estado = array_key_exists('id_Estado',$b) ? (int)$b['id_Estado'] : null;
  $cant   = array_key_exists('cantidad',$b) ? (int)$b['cantidad'] : null;

  // ✅ CORREGIDO: lee maxAlumno / max_por_alumno / etc.
  $maxAlumno = read_max_alumno($b, null);

  if ($id<=0) fail(400,'id_Material es obligatorio.');

  try{
    $cn->begin_transaction();

    $st=$cn->prepare("SELECT 1 FROM Materiales WHERE id_Material=?");
    $st->bind_param('i',$id); 
    $st->execute();
    if(!$st->get_result()->fetch_row()){
      $cn->rollback(); 
      fail(404,'No existe el material.');
    }
    $st->close();

    if ($nombre!==null && $nombre!==''){
      $st=$cn->prepare("SELECT 1 FROM Materiales WHERE nombre=? AND id_Material<>?");
      $st->bind_param('si',$nombre,$id); 
      $st->execute();
      if($st->get_result()->fetch_row()){
        $cn->rollback(); 
        fail(409,'Ya existe un material con ese nombre.');
      }
      $st->close();
    }

    // Construir SET dinámico
    $sets=[]; $types=''; $params=[];

    if($nombre!==null && $nombre!==''){ 
      $sets[]='nombre=?';          
      $types.='s'; 
      $params[]=&$nombre; 
    }
    if($estado!==null){                   
      $sets[]='id_Estado=?';      
      $types.='i'; 
      $params[]=&$estado; 
    }
    if($cant!==null){                     
      $sets[]='cantidad=?';       
      $types.='i'; 
      $params[]=&$cant;   
    }
    if($maxAlumno!==null){                
      $sets[]='max_por_alumno=?'; 
      $types.='i'; 
      $params[]=&$maxAlumno; 
    }

    if($sets){
      $sql="UPDATE Materiales SET ".implode(',', $sets)." WHERE id_Material=?";
      $types.='i'; 
      $params[]=&$id;
      $st=$cn->prepare($sql); 
      $st->bind_param($types, ...$params); 
      $st->execute(); 
      $st->close();
    }

    // Actualizar/limpiar clave
    if($clave!==null && $hasClaveTable){
      if($clave===''){
        $st=$cn->prepare("DELETE FROM MaterialClaves WHERE id_Material=?");
        $st->bind_param('i',$id); 
        $st->execute(); 
        $st->close();
      }else{
        if(strlen($clave)>10){ 
          $cn->rollback(); 
          fail(400,'La clave máximo 10 caracteres.'); 
        }
        $st=$cn->prepare("SELECT 1 FROM MaterialClaves WHERE clave=? AND id_Material<>?");
        $st->bind_param('si',$clave,$id); 
        $st->execute();
        if($st->get_result()->fetch_row()){
          $cn->rollback(); 
          fail(409,'La clave ya está en uso por otro material.');
        }
        $st->close();

        $st=$cn->prepare("
          INSERT INTO MaterialClaves (id_Material,clave) 
          VALUES (?,?) 
          ON DUPLICATE KEY UPDATE clave=VALUES(clave)
        ");
        $st->bind_param('is',$id,$clave); 
        $st->execute(); 
        $st->close();
      }
    }

    $cn->commit();
    ok(['mensaje'=>'Material actualizado.']);
  }catch(Throwable $e){ 
    $cn->rollback(); 
    fail(500,'PUT: '.$e->getMessage()); 
  }
}

/* ===== DELETE ===== */
if ($method==='DELETE'){
  if(isset($_GET['inactive'])) ok(['eliminadas'=>0]); // compat con apiPurgeInactive()

  $id = (int)($_GET['id_Material'] ?? 0);
  if ($id<=0) fail(400,'id_Material es obligatorio.');

  try{
    $st=$cn->prepare("DELETE FROM Materiales WHERE id_Material=?");
    $st->bind_param('i',$id); 
    $st->execute();
    if($st->affected_rows<1) fail(404,'No existía el material.');
    $st->close();
    ok(['mensaje'=>'Material eliminado.']);
  }catch(Throwable $e){ 
    fail(500,'DELETE: '.$e->getMessage()); 
  }
}

fail(405,'Método no soportado.');
