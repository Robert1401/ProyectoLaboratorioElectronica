<?php
// backend/check_numero_control.php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

$host = "127.0.0.1";
$user = "Laura";
$pass = "root";
$db   = "Laboratorio_Electronica";

$mysqli = @new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) { echo json_encode(["exists"=>false]); exit; }

$nc = isset($_GET["numeroControl"]) ? trim((string)$_GET["numeroControl"]) : "";
if (!preg_match('/^\d{8}$/', $nc)) { echo json_encode(["exists"=>false]); exit; }
$nc = (int)$nc;

$exists = false;

$stmt = $mysqli->prepare("SELECT 1 FROM Personas WHERE numeroControl = ? LIMIT 1");
$stmt->bind_param("i", $nc);
$stmt->execute(); $stmt->store_result();
if ($stmt->num_rows > 0) $exists = true;
$stmt->close();

if (!$exists) {
  $stmt = $mysqli->prepare("SELECT 1 FROM Usuarios WHERE numeroControl = ? LIMIT 1");
  $stmt->bind_param("i", $nc);
  $stmt->execute(); $stmt->store_result();
  if ($stmt->num_rows > 0) $exists = true;
  $stmt->close();
}

echo json_encode(["exists"=>$exists]);
$mysqli->close();
