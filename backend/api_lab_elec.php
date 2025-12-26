<?php
declare(strict_types=1);

/**
 * api_lab_elec.php
 * API única para:
 *  1) REGISTRO de compras (POST /)
 *  2) LISTADO de compras (GET  /?view=compras)
 *  3) DETALLE de compra o del día (GET /?view=compra&id=... | ?view=compra&fecha=YYYY-MM-DD)
 *  4) CATÁLOGO de materiales (GET /?view=materiales)
 *
 * Respuestas siempre en JSON (UTF-8) y con CORS simple.
 *
 * Esquema mínimo (úsalo si aún no existen tablas):
 *
 *  CREATE TABLE materiales (
 *    id_Material INT PRIMARY KEY,
 *    nombre      VARCHAR(200) NOT NULL,
 *    cantidad    INT NOT NULL DEFAULT 0
 *  );
 *
 *  CREATE TABLE compras (
 *    id_compra     INT AUTO_INCREMENT PRIMARY KEY,
 *    fecha         DATETIME NOT NULL,
 *    numeroControl VARCHAR(32) NOT NULL DEFAULT '0',
 *    auxiliar      VARCHAR(150) NOT NULL
 *  );
 *
 *  CREATE TABLE compra_items (
 *    id_item     INT AUTO_INCREMENT PRIMARY KEY,
 *    id_compra   INT NOT NULL,
 *    id_Material INT NOT NULL,
 *    cantidad    INT NOT NULL,
 *    gastoTotal  DECIMAL(12,2) NOT NULL DEFAULT 0,
 *    CONSTRAINT fk_ci_compra   FOREIGN KEY (id_compra)   REFERENCES compras(id_compra)   ON DELETE CASCADE,
 *    CONSTRAINT fk_ci_material FOREIGN KEY (id_Material) REFERENCES materiales(id_Material)
 *  );
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

/* ========= Conexión ========= */
$host = "127.0.0.1";
$user = "root";
$pass = "root";
$db   = "Laboratorio_Electronica";
$dsn  = "mysql:host={$host};dbname={$db};charset=utf8mb4";

try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok"=>false, "error"=>"DB_CONNECT_ERROR", "message"=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ========= Utils ========= */
function json_input(): array {
  $raw = file_get_contents('php://input') ?: '';
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}
function as_int($v, $min = 0): int {
  $n = filter_var($v, FILTER_VALIDATE_INT);
  return ($n !== false && $n >= $min) ? $n : $min;
}
function only_date($dt): string { return substr((string)$dt, 0, 10); }

/* ========= Enrutado ========= */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$view   = $_GET['view'] ?? '';

try {
  /* ----- GET materiales ----- */
  if ($method === 'GET' && $view === 'materiales') {
    $st = $pdo->query("SELECT id_Material, nombre, cantidad FROM materiales ORDER BY nombre ASC");
    echo json_encode(["ok"=>true, "materiales"=>$st->fetchAll()], JSON_UNESCAPED_UNICODE);
    exit;
  }

  /* ----- GET compras (listado; opcional filtro por fecha=YYYY-MM-DD) ----- */
  if ($method === 'GET' && $view === 'compras') {
    $fecha = isset($_GET['fecha']) ? trim((string)$_GET['fecha']) : '';
    if ($fecha !== '') {
      // listar SOLO las compras de esa fecha (con items)
      $st = $pdo->prepare("SELECT id_compra, fecha, numeroControl, auxiliar
                           FROM compras WHERE DATE(fecha)=? ORDER BY fecha DESC, id_compra DESC");
      $st->execute([$fecha]);
      $compras = $st->fetchAll();
    } else {
      // listar todas
      $compras = $pdo->query("SELECT id_compra, fecha, numeroControl, auxiliar
                              FROM compras ORDER BY fecha DESC, id_compra DESC")->fetchAll();
    }

    if (!$compras) { echo json_encode(["ok"=>true, "compras"=>[]], JSON_UNESCAPED_UNICODE); exit; }

    $ids = array_column($compras, 'id_compra');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $sti = $pdo->prepare("
      SELECT ci.id_compra, ci.id_Material, m.nombre, ci.cantidad, ci.gastoTotal
      FROM compra_items ci
      JOIN materiales m ON m.id_Material = ci.id_Material
      WHERE ci.id_compra IN ($in)
      ORDER BY m.nombre ASC
    ");
    $sti->execute($ids);
    $items = $sti->fetchAll();

    // Agrupar items por compra
    $by = [];
    foreach ($compras as $c) { $by[$c['id_compra']] = $c + ["items"=>[]]; }
    foreach ($items as $it) {
      $by[$it['id_compra']]["items"][] = [
        "id_Material" => (int)$it['id_Material'],
        "nombre"      => $it['nombre'],
        "cantidad"    => (int)$it['cantidad'],
        "gastoTotal"  => (float)$it['gastoTotal'],
      ];
    }
    echo json_encode(["ok"=>true, "compras"=>array_values($by)], JSON_UNESCAPED_UNICODE);
    exit;
  }

  /* ----- GET compra (detalle por id o agregado por fecha) ----- */
  if ($method === 'GET' && $view === 'compra') {
    $id    = isset($_GET['id']) ? as_int($_GET['id'], 1) : 0;
    $fecha = isset($_GET['fecha']) ? trim((string)$_GET['fecha']) : '';

    // MODO agregado por fecha: suma cantidades por material del día
    if ($fecha !== '') {
      $st = $pdo->prepare("SELECT id_compra, fecha, numeroControl, auxiliar
                           FROM compras WHERE DATE(fecha)=? ORDER BY id_compra ASC");
      $st->execute([$fecha]);
      $comprasDia = $st->fetchAll();
      if (!$comprasDia) {
        echo json_encode(["ok"=>true, "fecha"=>$fecha, "generalId"=>null, "auxiliar"=>null, "items"=>[]], JSON_UNESCAPED_UNICODE);
        exit;
      }
      $ids = array_column($comprasDia, 'id_compra');
      $in  = implode(',', array_fill(0, count($ids), '?'));
      $sti = $pdo->prepare("
        SELECT ci.id_compra, ci.id_Material, m.nombre, ci.cantidad
        FROM compra_items ci
        JOIN materiales m ON m.id_Material = ci.id_Material
        WHERE ci.id_compra IN ($in)
      ");
      $sti->execute($ids);
      $rows = $sti->fetchAll();

      // Sumar por material
      $sum = [];
      foreach ($rows as $r) {
        $k = (int)$r['id_Material'];
        if (!isset($sum[$k])) $sum[$k] = ["id_Material"=>$k, "nombre"=>$r['nombre'], "cantidad"=>0];
        $sum[$k]["cantidad"] += (int)$r['cantidad'];
      }
      ksort($sum);
      $generalId = (int)min($ids);               // el menor id del día
      $auxiliar  = $comprasDia[0]['auxiliar'];   // cualquiera del día (puedes ajustar lógica)
      echo json_encode([
        "ok"=>true,
        "fecha"=>$fecha,
        "generalId"=>$generalId,
        "auxiliar"=>$auxiliar,
        "items"=>array_values($sum)
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }

    // MODO detalle por id de compra
    if ($id > 0) {
      $sc = $pdo->prepare("SELECT id_compra, fecha, numeroControl, auxiliar FROM compras WHERE id_compra=?");
      $sc->execute([$id]);
      $c = $sc->fetch();
      if (!$c) { http_response_code(404); echo json_encode(["ok"=>false,"error"=>"NOT_FOUND"]); exit; }

      $si = $pdo->prepare("
        SELECT ci.id_Material, m.nombre, ci.cantidad, ci.gastoTotal
        FROM compra_items ci
        JOIN materiales m ON m.id_Material = ci.id_Material
        WHERE ci.id_compra=?
        ORDER BY m.nombre ASC
      ");
      $si->execute([$id]);
      $items = $si->fetchAll();
      echo json_encode(["ok"=>true, "compra"=>$c + ["items"=>$items]], JSON_UNESCAPED_UNICODE);
      exit;
    }

    http_response_code(400);
    echo json_encode(["ok"=>false, "error"=>"BAD_PARAMS", "message"=>"Usa ?id=... o ?fecha=YYYY-MM-DD"], JSON_UNESCAPED_UNICODE);
    exit;
  }

  /* ----- POST / (registrar compra) ----- */
  if ($method === 'POST') {
    $body = json_input();

    // Espera:
    // { fechaISO, numeroControl, auxiliar, items:[{id_Material,cantidad,gastoTotal}] }
    $fechaISO      = isset($body['fechaISO']) ? trim((string)$body['fechaISO']) : date('Y-m-d');
    $numeroControl = isset($body['numeroControl']) ? trim((string)$body['numeroControl']) : '0';
    $auxiliar      = isset($body['auxiliar']) ? trim((string)$body['auxiliar']) : 'Desconocido';
    $items         = isset($body['items']) && is_array($body['items']) ? $body['items'] : [];

    if (empty($items)) { http_response_code(400);
      echo json_encode(["ok"=>false,"error"=>"EMPTY_ITEMS","message"=>"No hay items para registrar."], JSON_UNESCAPED_UNICODE); exit; }

    $clean = [];
    foreach ($items as $i) {
      $idMat = as_int($i['id_Material'] ?? 0, 1);
      $cant  = as_int($i['cantidad'] ?? 0, 1);
      $gasto = isset($i['gastoTotal']) ? (float)$i['gastoTotal'] : 0.0;
      if ($idMat < 1 || $cant < 1) continue;
      $clean[] = ["id_Material"=>$idMat, "cantidad"=>$cant, "gastoTotal"=>$gasto];
    }
    if (!$clean) { http_response_code(400);
      echo json_encode(["ok"=>false,"error"=>"BAD_ITEMS","message"=>"Items inválidos."], JSON_UNESCAPED_UNICODE); exit; }

    $pdo->beginTransaction();
    try {
      $stC = $pdo->prepare("INSERT INTO compras (fecha, numeroControl, auxiliar) VALUES (?, ?, ?)");
      $stC->execute(["$fechaISO 00:00:00", $numeroControl, $auxiliar]);
      $idCompra = (int)$pdo->lastInsertId();

      $stI = $pdo->prepare("INSERT INTO compra_items (id_compra, id_Material, cantidad, gastoTotal) VALUES (?, ?, ?, ?)");
      $stU = $pdo->prepare("UPDATE materiales SET cantidad = cantidad + ? WHERE id_Material = ?");

      foreach ($clean as $it) {
        // validar material
        $chk = $pdo->prepare("SELECT 1 FROM materiales WHERE id_Material=?");
        $chk->execute([$it['id_Material']]);
        if (!$chk->fetchColumn()) throw new RuntimeException("Material inexistente: ".$it['id_Material']);

        $stI->execute([$idCompra, $it['id_Material'], $it['cantidad'], $it['gastoTotal']]);
        $stU->execute([$it['cantidad'], $it['id_Material']]);
      }
      $pdo->commit();

      echo json_encode([
        "ok"=>true,
        "message"=>"Compra registrada.",
        "compra"=>[
          "id_compra"=>$idCompra,
          "fecha"=>"$fechaISO 00:00:00",
          "numeroControl"=>$numeroControl,
          "auxiliar"=>$auxiliar,
          "items"=>$clean
        ]
      ], JSON_UNESCAPED_UNICODE);
      exit;

    } catch (Throwable $e) {
      $pdo->rollBack();
      http_response_code(500);
      echo json_encode(["ok"=>false,"error"=>"TX_ERROR","message"=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
      exit;
    }
  }

  /* ----- Ping por defecto ----- */
  if ($method === 'GET' && $view === '') {
    echo json_encode(["ok"=>true, "message"=>"API activa"], JSON_UNESCAPED_UNICODE);
    exit;
  }

  http_response_code(405);
  echo json_encode(["ok"=>false, "error"=>"METHOD_NOT_ALLOWED"], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["ok"=>false, "error"=>"UNHANDLED", "message"=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
