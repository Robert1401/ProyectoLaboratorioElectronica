<?php
header('Content-Type: application/json');

$host = "localhost";
$user = "root";
$pass = "";
$db = "tu_basedatos"; // cámbialo al nombre real

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  echo json_encode(["error" => "Error de conexión"]);
  exit;
}

$sql = "SELECT id, titulo, motivo, fecha, hora, participantes, cupo_maximo, asesor
        FROM asesorias";
$result = $conn->query($sql);

$asesorias = [];
while ($row = $result->fetch_assoc()) {
  $asesorias[] = $row;
}

echo json_encode($asesorias);
$conn->close();
?>
