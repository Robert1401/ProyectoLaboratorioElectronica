<?php
header("Content-Type: application/json; charset=utf-8");

// 🔹 Conexión
$servername = "127.0.0.1";
$username = "root";
$password = "roblox12";
$dbname = "Laboratorio_Electronica";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  echo json_encode(["error" => "Error en la conexión: " . $conn->connect_error]);
  exit;
}

$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {
  // 🔹 Obtener todas las carreras
  case 'GET':
    $sql = "SELECT id_Carrera, nombre FROM Carreras ORDER BY id_Carrera";
    $result = $conn->query($sql);
    $carreras = [];
    while ($row = $result->fetch_assoc()) {
      $carreras[] = $row;
    }
    echo json_encode($carreras);
    break;

  // 🔹 Agregar carrera
  case 'POST':
    $data = json_decode(file_get_contents("php://input"), true);
    $nombre = $data["nombre"] ?? '';
    if (empty($nombre)) {
      echo json_encode(["error" => "Nombre vacío"]);
      exit;
    }

    $stmt = $conn->prepare("INSERT INTO Carreras (id_Estado, nombre) VALUES (?, ?)");
    $id_estado = 1; // puedes cambiarlo según tu caso
    $stmt->bind_param("is", $id_estado, $nombre);

    if ($stmt->execute()) {
      echo json_encode(["mensaje" => "Carrera agregada correctamente"]);
    } else {
      echo json_encode(["error" => "Error al agregar carrera: " . $conn->error]);
    }
    $stmt->close();
    break;

  // 🔹 Eliminar carrera
  case 'DELETE':
    parse_str(file_get_contents("php://input"), $data);
    $id = intval($data["id_Carrera"] ?? 0);
    if ($id <= 0) {
      echo json_encode(["error" => "ID inválido"]);
      exit;
    }

    $stmt = $conn->prepare("DELETE FROM Carreras WHERE id_Carrera = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
      echo json_encode(["mensaje" => "Carrera eliminada correctamente"]);
    } else {
      echo json_encode(["error" => "Error al eliminar carrera"]);
    }
    $stmt->close();
    break;
}

$conn->close();
?>
