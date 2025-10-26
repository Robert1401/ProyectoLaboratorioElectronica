<?php
header('Content-Type: application/json');
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db = "tu_basedatos"; // cámbialo por el nombre de tu base de datos

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  echo json_encode(["estado" => "error", "mensaje" => "Error de conexión con la base de datos"]);
  exit;
}

// En producción, el id del alumno debería venir de la sesión
$alumno_id = $_SESSION["id_alumno"] ?? 1;
$id_asesoria = $_POST['id_asesoria'] ?? 0;

// Verificar que la asesoría existe
$cupo = $conn->query("SELECT participantes, cupo_maximo FROM asesorias WHERE id=$id_asesoria")->fetch_assoc();
if (!$cupo) {
  echo json_encode(["estado" => "error", "mensaje" => "La asesoría no existe."]);
  exit;
}

// Verificar si hay cupo
if ($cupo["participantes"] >= $cupo["cupo_maximo"]) {
  echo json_encode(["estado" => "error", "mensaje" => "No hay cupo disponible en esta asesoría."]);
  exit;
}

// Verificar si el alumno ya está inscrito
$check = $conn->prepare("SELECT * FROM asistentesasesoria WHERE id_alumno=? AND id_asesoria=?");
$check->bind_param("ii", $alumno_id, $id_asesoria);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
  echo json_encode(["estado" => "error", "mensaje" => "Ya estás inscrito en esta asesoría."]);
  exit;
}

// Registrar inscripción
$stmt = $conn->prepare("INSERT INTO asistentesasesoria (id_asesoria, id_alumno) VALUES (?, ?)");
$stmt->bind_param("ii", $id_asesoria, $alumno_id);

if ($stmt->execute()) {
  // Actualizar número de participantes
  $conn->query("UPDATE asesorias SET participantes = participantes + 1 WHERE id = $id_asesoria");
  echo json_encode(["estado" => "ok", "mensaje" => "Te has inscrito correctamente."]);
} else {
  echo json_encode(["estado" => "error", "mensaje" => "Ocurrió un error al inscribirte."]);
}

$stmt->close();
$conn->close();
?>
