<?php
header('Content-Type: application/json');

// CONFIGURACIÓN DE CONEXIÓN
$host = "localhost";
$user = "root";
$pass = "";
$db = "tu_basedatos"; // cambia esto al nombre real

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  echo json_encode(["estado" => "error", "mensaje" => "Error de conexión"]);
  exit;
}

$accion = $_POST['accion'] ?? '';

if ($accion === 'agregar') {
  $titulo = $_POST['titulo'];
  $tema = $_POST['tema'];
  $fecha = $_POST['fecha'];
  $hora = $_POST['hora'];
  $asistentes = $_POST['asistentes'];

  $sql = "INSERT INTO asesorias (titulo, motivo, fecha, hora, participantes) VALUES (?, ?, ?, ?, ?)";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ssssi", $titulo, $tema, $fecha, $hora, $asistentes);

  if ($stmt->execute()) {
    echo json_encode(["estado" => "ok", "mensaje" => "Asesoría agregada correctamente"]);
  } else {
    echo json_encode(["estado" => "error", "mensaje" => "Error al agregar asesoría"]);
  }
  $stmt->close();
}

if ($accion === 'modificar') {
  $id = $_POST['id'] ?? 0;
  $titulo = $_POST['titulo'];
  $tema = $_POST['tema'];
  $fecha = $_POST['fecha'];
  $hora = $_POST['hora'];
  $asistentes = $_POST['asistentes'];

  $sql = "UPDATE asesorias SET titulo=?, motivo=?, fecha=?, hora=?, participantes=? WHERE id=?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("ssssii", $titulo, $tema, $fecha, $hora, $asistentes, $id);

  if ($stmt->execute()) {
    echo json_encode(["estado" => "ok", "mensaje" => "Asesoría modificada correctamente"]);
  } else {
    echo json_encode(["estado" => "error", "mensaje" => "Error al modificar asesoría"]);
  }
  $stmt->close();
}

$conn->close();
?>
