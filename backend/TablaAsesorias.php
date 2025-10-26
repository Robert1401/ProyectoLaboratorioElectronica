<?php
header('Content-Type: application/json');

$host = "localhost";
$user = "root";
$pass = "";
$db = "tu_basedatos"; // cámbialo por el nombre real

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
  echo json_encode(["estado" => "error", "mensaje" => "Error de conexión"]);
  exit;
}

$sql = "SELECT id, titulo, motivo, fecha, hora, participantes FROM asesorias";
$result = $conn->query($sql);

$asesorias = [];

while ($row = $result->fetch_assoc()) {
  $asesorias[] = $row;
}

echo json_encode($asesorias);
$conn->close();
?>
