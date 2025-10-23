<?php
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
function fail(int $code, string $msg): void { http_response_code($code); echo json_encode(['error'=>$msg], JSON_UNESCAPED_UNICODE); exit; }
function ok(array $payload): void { echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit; }

/* ======= Config DB ======= */
function resolve_db_config(): array {
  $p = fn(string $k) => $_GET[$k] ?? $_POST[$k] ?? null;
  if (($h=$p('db_host')) && ($n=$p('db_name')) && ($u=$p('db_user')) !== null) return ['host'=>$h,'name'=>$n,'user'=>$u,'pass'=>$p('db_pass') ?? '','source'=>'url'];
  $eh=getenv('LE_DB_HOST'); $en=getenv('LE_DB_NAME'); $eu=getenv('LE_DB_USER'); $ep=getenv('LE_DB_PASS');
  if ($eh && $en && $eu !== false) return ['host'=>$eh,'name'=>$en,'user'=>$eu,'pass'=>$ep?:'','source'=>'env'];
  $iniPath = __DIR__ . '/config/db.ini';
  if (is_file($iniPath)) {
    $ini = parse_ini_file($iniPath, false, INI_SCANNER_TYPED);
    if (!empty($ini['host']) && !empty($ini['name']) && isset($ini['user'])) return ['host'=>$ini['host'],'name'=>$ini['name'],'user'=>$ini['user'],'pass'=>$ini['pass']??'','source'=>'ini'];
  }
  return ['host'=>'127.0.0.1','name'=>'Laboratorio_Electronica','user'=>'root','pass'=>'root','source'=>'default'];
}
$cfg = resolve_db_config();

/* ======= Conexión ======= */
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
  PDO::ATTR_PERSISTENT         => false,
];
try {
  $pdo = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['name']), $cfg['user'], $cfg['pass'], $options);
} catch (Throwable $e) { fail(500, 'Error de conexión a BD: '.$e->getMessage()); }

/* ======= Helpers ======= */
function has_column(PDO $pdo, string $table, string $column): bool {
  $q = $pdo->prepare("SELECT COUNT(*) c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
  $q->execute([$table, $column]); return (int)$q->fetch()['c'] > 0;
}
function is_auto_increment(PDO $pdo, string $table, string $column): bool {
  $q = $pdo->prepare("SELECT EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
  $q->execute([$table, $column]); $row=$q->fetch(); return $row && stripos($row['EXTRA'] ?? '', 'auto_increment') !== false;
}
function compras_fecha_col(PDO $pdo): string {
  if (has_column($pdo, 'Compras', 'fechaIngreso')) return 'fechaIngreso';
  if (has_column($pdo, 'Compras', 'fecha_Hora_Ingreso')) return 'fecha_Hora_Ingreso';
  fail(500, "La tabla Compras no tiene columna de fecha ('fechaIngreso' o 'fecha_Hora_Ingreso').");
}
/** Devuelve [numeroControl, nombreCompleto] para un auxiliar. */
function resolve_auxiliar(PDO $pdo, ?int $numeroControl): array {
  $base = "SELECT p.numeroControl, CONCAT(p.nombre,' ',p.apellidoPaterno,' ',p.apellidoMaterno) AS nombreCompleto
           FROM Personas p JOIN Roles r ON r.id_Rol=p.id_Rol WHERE r.nombre='Auxiliar'";
  if ($numeroControl) {
    $st = $pdo->prepare($base." AND p.numeroControl=? LIMIT 1"); $st->execute([$numeroControl]); $row=$st->fetch();
    if ($row) return [(int)$row['numeroControl'], (string)$row['nombreCompleto']];
  }
  $row = $pdo->query($base." ORDER BY p.numeroControl ASC LIMIT 1")->fetch();
  if (!$row) fail(404, 'No hay auxiliares registrados');
  return [(int)$row['numeroControl'], (string)$row['nombreCompleto']];
}

/* ======= Router ======= */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ---------- GET ---------- */
if ($method === 'GET') {
  if (isset($_GET['config'])) ok(['ok'=>true,'usando'=>['host'=>$cfg['host'],'name'=>$cfg['name'],'user'=>$cfg['user'],'source'=>$cfg['source']],'nota'=>'(sin password)']);

  if (isset($_GET['auxiliar'])) {
    $num = isset($_GET['numeroControl']) ? (int)$_GET['numeroControl'] : null;
    [$nc, $nombre] = resolve_auxiliar($pdo, $num);
    ok(['ok'=>true,'numeroControl'=>$nc,'nombre'=>$nombre]);
  }

  if (isset($_GET['materiales'])) {
    $rows = $pdo->query("SELECT id_Material, nombre, cantidad FROM Materiales ORDER BY nombre")->fetchAll() ?? [];
    ok($rows);
  }

  if (isset($_GET['compras'])) {
    $limit = isset($_GET['limit']) ? max(1,(int)$_GET['limit']) : 50;
    $fcol  = compras_fecha_col($pdo);
    $st = $pdo->prepare("
      SELECT c.id_compra, c.$fcol AS fechaIngreso,
             m.id_Material, m.nombre AS nombreMaterial,
             mc.cantidad AS cantidadComprada,
             m.cantidad  AS stockActual,
             mc.gastoTotal
      FROM MaterialesComprados mc
      JOIN Compras c    ON c.id_compra   = mc.id_compra
      JOIN Materiales m ON m.id_Material = mc.id_Material
      ORDER BY c.$fcol DESC, c.id_compra DESC, m.nombre ASC
      LIMIT :lim
    ");
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    ok($st->fetchAll() ?? []);
  }

  ok(['ok'=>true,'mensaje'=>'Endpoint activo']);
}

/* ---------- POST ---------- */
if ($method === 'POST') {
  $raw  = file_get_contents('php://input');
  if ($raw === '' || $raw === false) fail(400,'Body vacío');
  $data = json_decode($raw, true);
  if (!is_array($data)) fail(400,'JSON inválido');

  /* --- fijar cantidad EXACTA de una línea (editar) --- */
  if (($data['action'] ?? '') === 'set_item_qty') {
    $idc = (int)($data['id_compra']   ?? 0);
    $idm = (int)($data['id_Material'] ?? 0);
    $new = (int)($data['nuevaCantidad'] ?? -1);
    if ($idc<=0 || $idm<=0 || $new<0) fail(422,'Parámetros inválidos');

    try {
      $pdo->beginTransaction();

      $st = $pdo->prepare(
        "SELECT cantidad FROM MaterialesComprados
         WHERE id_compra=? AND id_Material=? FOR UPDATE"
      );
      $st->execute([$idc,$idm]);
      $row=$st->fetch();
      if (!$row) { $pdo->rollBack(); fail(404,'Línea no encontrada'); }

      $actual = (int)$row['cantidad'];
      $delta  = $new - $actual;

      if ($delta === 0) { $pdo->commit(); ok(['ok'=>true,'mensaje'=>'Sin cambios']); }

      if ($new === 0) {
        $pdo->prepare("DELETE FROM MaterialesComprados WHERE id_compra=? AND id_Material=?")
            ->execute([$idc,$idm]);
        $pdo->prepare("UPDATE Materiales SET cantidad = cantidad - ? WHERE id_Material=?")
            ->execute([$actual,$idm]);

        $chk = $pdo->prepare("SELECT 1 FROM MaterialesComprados WHERE id_compra=? LIMIT 1");
        $chk->execute([$idc]);
        if (!$chk->fetch()) $pdo->prepare("DELETE FROM Compras WHERE id_compra=?")->execute([$idc]);

        $pdo->commit();
        ok(['ok'=>true,'mensaje'=>'Línea eliminada y stock actualizado']);
      }

      if ($delta > 0) {
        $pdo->prepare("UPDATE MaterialesComprados SET cantidad = cantidad + ? WHERE id_compra=? AND id_Material=?")
            ->execute([$delta,$idc,$idm]);
        $pdo->prepare("UPDATE Materiales SET cantidad = cantidad + ? WHERE id_Material=?")
            ->execute([$delta,$idm]);
      } else {
        $abs = -$delta;
        if ($abs > $actual) { $pdo->rollBack(); fail(422,'La reducción supera la cantidad actual'); }
        $pdo->prepare("UPDATE MaterialesComprados SET cantidad = cantidad - ? WHERE id_compra=? AND id_Material=?")
            ->execute([$abs,$idc,$idm]);
        $pdo->prepare("UPDATE Materiales SET cantidad = cantidad - ? WHERE id_Material=?")
            ->execute([$abs,$idm]);
      }

      $pdo->commit();
      ok(['ok'=>true,'mensaje'=>'Cantidad actualizada']);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      fail(500,'No se pudo actualizar la cantidad: '.$e->getMessage());
    }
  }

  /* --- eliminar total de la línea --- */
  if (($data['action'] ?? '') === 'delete_item') {
    $idc = (int)($data['id_compra']   ?? 0);
    $idm = (int)($data['id_Material'] ?? 0);
    if ($idc<=0 || $idm<=0) fail(422,'Parámetros inválidos');
    try {
      $pdo->beginTransaction();
      $st = $pdo->prepare("SELECT cantidad FROM MaterialesComprados WHERE id_compra=? AND id_Material=?");
      $st->execute([$idc,$idm]); $row=$st->fetch();
      if (!$row) { $pdo->rollBack(); fail(404,'Línea no encontrada'); }
      $cant = (int)$row['cantidad'];

      $pdo->prepare("DELETE FROM MaterialesComprados WHERE id_compra=? AND id_Material=?")->execute([$idc,$idm]);
      $pdo->prepare("UPDATE Materiales SET cantidad = cantidad - ? WHERE id_Material=?")->execute([$cant,$idm]);

      $chk = $pdo->prepare("SELECT 1 FROM MaterialesComprados WHERE id_compra=? LIMIT 1"); $chk->execute([$idc]);
      if (!$chk->fetch()) $pdo->prepare("DELETE FROM Compras WHERE id_compra=?")->execute([$idc]);

      $pdo->commit();
      ok(['ok'=>true,'mensaje'=>'Ítem eliminado y stock revertido']);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      fail(500,'No se pudo eliminar: '.$e->getMessage());
    }
  }

  /* --- eliminar parcial de la línea --- */
  if (($data['action'] ?? '') === 'decrement_item') {
    $idc = (int)($data['id_compra']   ?? 0);
    $idm = (int)($data['id_Material'] ?? 0);
    $qty = (int)($data['cantidad']    ?? 0);
    if ($idc<=0 || $idm<=0 || $qty<=0) fail(422,'Parámetros inválidos');

    try {
      $pdo->beginTransaction();
      $st = $pdo->prepare("SELECT cantidad FROM MaterialesComprados WHERE id_compra=? AND id_Material=? FOR UPDATE");
      $st->execute([$idc,$idm]); $row=$st->fetch();
      if (!$row) { $pdo->rollBack(); fail(404,'Línea no encontrada'); }
      $actual = (int)$row['cantidad'];
      if ($qty > $actual) { $pdo->rollBack(); fail(422,'Cantidad a eliminar mayor que la existente'); }

      if ($qty === $actual) {
        $pdo->prepare("DELETE FROM MaterialesComprados WHERE id_compra=? AND id_Material=?")->execute([$idc,$idm]);
      } else {
        $pdo->prepare("UPDATE MaterialesComprados SET cantidad = cantidad - ? WHERE id_compra=? AND id_Material=?")
            ->execute([$qty,$idc,$idm]);
      }
      // revertir stock
      $pdo->prepare("UPDATE Materiales SET cantidad = cantidad - ? WHERE id_Material=?")->execute([$qty,$idm]);

      // si ya no hay líneas en la compra, borra cabecera
      $chk = $pdo->prepare("SELECT 1 FROM MaterialesComprados WHERE id_compra=? LIMIT 1"); $chk->execute([$idc]);
      if (!$chk->fetch()) $pdo->prepare("DELETE FROM Compras WHERE id_compra=?")->execute([$idc]);

      $pdo->commit();
      ok(['ok'=>true,'mensaje'=>'Cantidad eliminada y stock actualizado']);
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      fail(500,'No se pudo eliminar parcialmente: '.$e->getMessage());
    }
  }

  /* --- crear compra --- */
  $fecha         = trim((string)($data['fecha'] ?? ''));
  $numeroControl = isset($data['numeroControl']) ? (int)$data['numeroControl'] : null;
  $items         = (array)($data['items'] ?? []);
  if ($fecha==='')          fail(422,'Falta la fecha');
  if (!count($items))       fail(422,'Debe agregar al menos un material');

  [$nc, $auxNombre] = resolve_auxiliar($pdo, $numeroControl);
  $fechaDT = preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha) ? ($fecha.' 00:00:00') : $fecha;

  try {
    $pdo->beginTransaction();

    $fcol     = compras_fecha_col($pdo);
    $tieneNC  = has_column($pdo,'Compras','numeroControl');
    $tieneEC  = has_column($pdo,'Compras','id_Estado');
    $tieneED  = has_column($pdo,'MaterialesComprados','id_Estado');

    $ai = is_auto_increment($pdo,'Compras','id_compra');
    if ($ai) {
      if ($tieneNC && $tieneEC) {
        $pdo->prepare("INSERT INTO Compras ($fcol, numeroControl, id_Estado) VALUES (?, ?, 1)")->execute([$fechaDT,$nc]);
      } elseif ($tieneNC) {
        $pdo->prepare("INSERT INTO Compras ($fcol, numeroControl) VALUES (?, ?)")->execute([$fechaDT,$nc]);
      } elseif ($tieneEC) {
        $pdo->prepare("INSERT INTO Compras ($fcol, id_Estado) VALUES (?, 1)")->execute([$fechaDT]);
      } else {
        $pdo->prepare("INSERT INTO Compras ($fcol) VALUES (?)")->execute([$fechaDT]);
      }
      $id_compra = (int)$pdo->lastInsertId();
    } else {
      $id_compra = (int)$pdo->query("SELECT COALESCE(MAX(id_compra),0)+1 AS nx FROM Compras")->fetch()['nx'];
      if ($tieneNC && $tieneEC) {
        $pdo->prepare("INSERT INTO Compras (id_compra, $fcol, numeroControl, id_Estado) VALUES (?, ?, ?, 1)")
            ->execute([$id_compra,$fechaDT,$nc]);
      } elseif ($tieneNC) {
        $pdo->prepare("INSERT INTO Compras (id_compra, $fcol, numeroControl) VALUES (?, ?, ?)")
            ->execute([$id_compra,$fechaDT,$nc]);
      } elseif ($tieneEC) {
        $pdo->prepare("INSERT INTO Compras (id_compra, $fcol, id_Estado) VALUES (?, ?, 1)")
            ->execute([$id_compra,$fechaDT]);
      } else {
        $pdo->prepare("INSERT INTO Compras (id_compra, $fcol) VALUES (?, ?)")
            ->execute([$id_compra,$fechaDT]);
      }
    }

    $selMat = $pdo->prepare("SELECT 1 FROM Materiales WHERE id_Material=?");
    $insDet = $tieneED
      ? $pdo->prepare("INSERT INTO MaterialesComprados (id_Material, id_compra, cantidad, gastoTotal, id_Estado) VALUES (?, ?, ?, ?, 1)")
      : $pdo->prepare("INSERT INTO MaterialesComprados (id_Material, id_compra, cantidad, gastoTotal) VALUES (?, ?, ?, ?)");
    $updStk = $pdo->prepare("UPDATE Materiales SET cantidad = cantidad + ? WHERE id_Material=?");

    foreach ($items as $i=>$it) {
      $idm = (int)($it['id_Material'] ?? 0);
      $qty = (int)($it['cantidad']    ?? 0);
      $gt  = (float)($it['gastoTotal'] ?? 0);
      if ($idm<=0) fail(422,"Item #".($i+1).": id_Material inválido");
      if ($qty<=0) fail(422,"Item #".($i+1).": cantidad debe ser > 0");
      if (!is_finite($gt)) fail(422,"Item #".($i+1).": gastoTotal inválido");

      $selMat->execute([$idm]); if (!$selMat->fetch()) fail(422,"Item #".($i+1).": material $idm no existe");

      $insDet->execute([$idm,$id_compra,$qty,$gt]);
      $updStk->execute([$qty,$idm]);
    }

    $pdo->commit();
    ok(['ok'=>true,'mensaje'=>'Compra guardada correctamente','id_compra'=>$id_compra,'auxiliar'=>['numeroControl'=>$nc,'nombre'=>$auxNombre]]);
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fail(500,'No se pudo guardar la compra: '.$e->getMessage());
  }
}

/* ------- fallback ------- */
fail(405,'Método no permitido');
