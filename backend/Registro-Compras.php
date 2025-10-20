<?php
/**
 * backend/Registro-Compras.php
 * API JSON para Registro de Compras de Materiales
 * - GET  ?materiales=1
 * - GET  ?compras=1&limit=50
 * - GET  ?config=1
 * - GET  ?auxiliar=1[&numeroControl=####]
 * - POST {fecha, items[], numeroControl?}
 * - POST {action:"delete_item", id_compra, id_Material}
 */
declare(strict_types=1);

/* ======= Cabeceras / CORS ======= */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

/* ======= Errores SIEMPRE en JSON ======= */
ini_set('display_errors', '0');
error_reporting(E_ALL);

set_error_handler(function($no, $str, $file, $line){
  http_response_code(500);
  echo json_encode(['error'=>'PHP error','detalle'=>"$str @ $file:$line"], JSON_UNESCAPED_UNICODE);
  exit;
});
set_exception_handler(function(Throwable $e){
  http_response_code(500);
  echo json_encode(['error'=>'Excepción','detalle'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
});

/* ======= Utils ======= */
function fail(int $code, string $msg): void {
  http_response_code($code);
  echo json_encode(['error'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}
function ok(array $payload): void { echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }

/* ======= Config DB: URL -> ENV -> INI -> defaults ======= */
function resolve_db_config(): array {
  $p = fn(string $k) => $_GET[$k] ?? $_POST[$k] ?? null;

  if (($h=$p('db_host')) && ($n=$p('db_name')) && ($u=$p('db_user')) !== null) {
    return ['host'=>$h,'name'=>$n,'user'=>$u,'pass'=>$p('db_pass') ?? '','source'=>'url'];
  }

  $eh=getenv('LE_DB_HOST'); $en=getenv('LE_DB_NAME'); $eu=getenv('LE_DB_USER'); $ep=getenv('LE_DB_PASS');
  if ($eh && $en && $eu !== false) return ['host'=>$eh,'name'=>$en,'user'=>$eu,'pass'=>$ep?:'','source'=>'env'];

  $iniPath = __DIR__ . '/config/db.ini';
  if (is_file($iniPath)) {
    $ini = parse_ini_file($iniPath, false, INI_SCANNER_TYPED);
    if (!empty($ini['host']) && !empty($ini['name']) && isset($ini['user'])) {
      return ['host'=>$ini['host'],'name'=>$ini['name'],'user'=>$ini['user'],'pass'=>$ini['pass']??'','source'=>'ini'];
    }
  }

  return ['host'=>'127.0.0.1','name'=>'Laboratorio_Electronica','user'=>'root','pass'=>'root','source'=>'default'];
}
$cfg = resolve_db_config();

/* ======= Conexión PDO ======= */
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
  PDO::ATTR_PERSISTENT         => false,
];
try {
  $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['name']);
  $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $options);
} catch (Throwable $e) {
  fail(500, 'Error de conexión a BD: '.$e->getMessage());
}

/* ======= Helpers de esquema / negocio ======= */
function has_column(PDO $pdo, string $table, string $column): bool {
  $q = $pdo->prepare(
    "SELECT COUNT(*) c
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
  );
  $q->execute([$table, $column]);
  return (int)$q->fetch()['c'] > 0;
}

/** Devuelve [numeroControl, nombreCompleto] para un auxiliar. */
function resolve_auxiliar(PDO $pdo, ?int $numeroControl): array {
  $sqlBase = "SELECT p.numeroControl,
                     CONCAT(p.nombre,' ',p.apellidoPaterno,' ',p.apellidoMaterno) AS nombreCompleto
              FROM Personas p
              JOIN Roles r ON r.id_Rol = p.id_Rol
              WHERE r.nombre = 'Auxiliar'";

  if ($numeroControl) {
    $st = $pdo->prepare($sqlBase." AND p.numeroControl = ? LIMIT 1");
    $st->execute([$numeroControl]);
    $row = $st->fetch();
    if ($row) return [(int)$row['numeroControl'], (string)$row['nombreCompleto']];
  }

  $row = $pdo->query($sqlBase." ORDER BY p.numeroControl ASC LIMIT 1")->fetch();
  if (!$row) fail(404, 'No hay auxiliares registrados');
  return [(int)$row['numeroControl'], (string)$row['nombreCompleto']];
}

/** Resuelve nombre de columna de fecha en Compras. */
function compras_fecha_col(PDO $pdo): string {
  if (has_column($pdo, 'Compras', 'fechaIngreso')) return 'fechaIngreso';
  if (has_column($pdo, 'Compras', 'fecha_Hora_Ingreso')) return 'fecha_Hora_Ingreso';
  fail(500, "La tabla Compras no tiene columna de fecha ('fechaIngreso' o 'fecha_Hora_Ingreso').");
}

/* ======= Método ======= */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ======= GET ======= */
if ($method === 'GET') {

  // Diagnóstico de conexión (sin password)
  if (isset($_GET['config'])) {
    ok([
      'ok'=>true,
      'usando'=>['host'=>$cfg['host'],'name'=>$cfg['name'],'user'=>$cfg['user'],'source'=>$cfg['source']],
      'nota'=>'La contraseña no se muestra por seguridad'
    ]);
  }

  // Auxiliar auto para UI
  if (isset($_GET['auxiliar'])) {
    $num = isset($_GET['numeroControl']) ? (int)$_GET['numeroControl'] : null;
    [$nc, $nombre] = resolve_auxiliar($pdo, $num);
    ok(['ok'=>true,'numeroControl'=>$nc,'nombre'=>$nombre]);
  }

  // Catálogo de materiales
  if (isset($_GET['materiales'])) {
    try {
      $rows = $pdo->query("SELECT id_Material, nombre, cantidad FROM Materiales ORDER BY nombre")->fetchAll() ?? [];
      ok($rows);
    } catch (Throwable $e) {
      fail(500, 'No se pudieron obtener materiales: '.$e->getMessage());
    }
  }

  // Historial de compras (JOIN)
  if (isset($_GET['compras'])) {
    $limit = isset($_GET['limit']) ? max(1,(int)$_GET['limit']) : 50;
    $colFecha = compras_fecha_col($pdo);
    try {
      $st = $pdo->prepare("
        SELECT c.id_compra, c.$colFecha AS fechaIngreso,
               m.id_Material, m.nombre AS nombreMaterial,
               mc.cantidad AS cantidadComprada,
               m.cantidad  AS stockActual,
               mc.gastoTotal
        FROM MaterialesComprados mc
        JOIN Compras c    ON c.id_compra   = mc.id_compra
        JOIN Materiales m ON m.id_Material = mc.id_Material
        ORDER BY c.$colFecha DESC, c.id_compra DESC, m.nombre ASC
        LIMIT :lim
      ");
      $st->bindValue(':lim', $limit, PDO::PARAM_INT);
      $st->execute();
      ok($st->fetchAll() ?? []);
    } catch (Throwable $e) {
      fail(500, 'No se pudo obtener historial de compras: '.$e->getMessage());
    }
  }

  // Ping
  ok(['ok'=>true,'mensaje'=>'Endpoint activo']);
}

/* ======= POST ======= */
if ($method === 'POST') {
  $raw = file_get_contents('php://input');
  if ($raw === '' || $raw === false) fail(400, 'Body vacío');
  $data = json_decode($raw, true);
  if (!is_array($data)) fail(400, 'JSON inválido');

  // Eliminar ítem
  if (($data['action'] ?? '') === 'delete_item') {
    $id_compra  = (int)($data['id_compra']   ?? 0);
    $idMaterial = (int)($data['id_Material'] ?? 0);
    if ($id_compra <= 0 || $idMaterial <= 0) fail(422, 'Parámetros inválidos');

    try {
      $pdo->beginTransaction();

      $st = $pdo->prepare("SELECT cantidad FROM MaterialesComprados WHERE id_compra=? AND id_Material=?");
      $st->execute([$id_compra, $idMaterial]);
      $row = $st->fetch();
      if (!$row) { $pdo->rollBack(); fail(404, 'Línea no encontrada'); }
      $cant = (int)$row['cantidad'];

      $pdo->prepare("DELETE FROM MaterialesComprados WHERE id_compra=? AND id_Material=?")
          ->execute([$id_compra, $idMaterial]);

      $pdo->prepare("UPDATE Materiales SET cantidad = cantidad - ? WHERE id_Material=?")
          ->execute([$cant, $idMaterial]);

      $chk = $pdo->prepare("SELECT 1 FROM MaterialesComprados WHERE id_compra=? LIMIT 1");
      $chk->execute([$id_compra]);
      if (!$chk->fetch()) {
        $pdo->prepare("DELETE FROM Compras WHERE id_compra=?")->execute([$id_compra]);
      }

      $pdo->commit();
      ok(['ok'=>true,'mensaje'=>'Ítem eliminado y stock revertido']);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      fail(500, 'No se pudo eliminar: '.$e->getMessage());
    }
  }

  // Crear compra
  $fecha         = trim((string)($data['fecha'] ?? ''));
  $numeroControl = isset($data['numeroControl']) ? (int)$data['numeroControl'] : null; // opcional
  $items         = (array)($data['items'] ?? []);

  if ($fecha === '')       fail(422, 'Falta la fecha');
  if (count($items) === 0) fail(422, 'Debe agregar al menos un material');

  // Resolver auxiliar si no viene (o validar si viene)
  [$nc, $auxNombre] = resolve_auxiliar($pdo, $numeroControl);

  // Normalizar fecha a DATETIME
  $fechaDT = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? ($fecha.' 00:00:00') : $fecha;

  try {
    $pdo->beginTransaction();

    $colFecha = compras_fecha_col($pdo);
    $tieneNC  = has_column($pdo, 'Compras', 'numeroControl');

    if ($tieneNC) {
      $insCompra = $pdo->prepare("INSERT INTO Compras ($colFecha, numeroControl) VALUES (?, ?)");
      $insCompra->execute([$fechaDT, $nc]);
    } else {
      $insCompra = $pdo->prepare("INSERT INTO Compras ($colFecha) VALUES (?)");
      $insCompra->execute([$fechaDT]);
    }
    $id_compra = (int)$pdo->lastInsertId();

    // Preparados
    $selMat   = $pdo->prepare("SELECT id_Material FROM Materiales WHERE id_Material=?");
    $insDet   = $pdo->prepare("INSERT INTO MaterialesComprados (id_Material, id_compra, cantidad, gastoTotal) VALUES (?, ?, ?, ?)");
    $updStock = $pdo->prepare("UPDATE Materiales SET cantidad = cantidad + ? WHERE id_Material=?");

    foreach ($items as $i => $it) {
      $id_Material = (int)($it['id_Material'] ?? 0);
      $cantidad    = (int)($it['cantidad']    ?? 0);
      $gastoTotal  = (float)($it['gastoTotal'] ?? 0.0);

      if ($id_Material <= 0)       fail(422, "Item #".($i+1).": id_Material inválido");
      if ($cantidad <= 0)          fail(422, "Item #".($i+1).": cantidad debe ser > 0");
      if (!is_finite($gastoTotal)) fail(422, "Item #".($i+1).": gastoTotal inválido");

      $selMat->execute([$id_Material]);
      if (!$selMat->fetch()) fail(422, "Item #".($i+1).": material $id_Material no existe");

      $insDet->execute([$id_Material, $id_compra, $cantidad, $gastoTotal]);
      $updStock->execute([$cantidad, $id_Material]);
    }

    $pdo->commit();
    ok([
      'ok'=>true,
      'mensaje'=>'Compra guardada correctamente',
      'id_compra'=>$id_compra,
      'auxiliar'=>['numeroControl'=>$nc, 'nombre'=>$auxNombre]
    ]);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fail(500, 'No se pudo guardar la compra: '.$e->getMessage());
  }
}

/* ======= Método no permitido ======= */
fail(405, 'Método no permitido');
