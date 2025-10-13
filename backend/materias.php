<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// 🔹 Conexión a la base de datos
$servername = "127.0.0.1";
$username = "root";
$password = "root"; // Cambia si tu contraseña es distinta
$dbname = "Laboratorio_Electronica";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["error" => "Error de conexión: " . $conn->connect_error]));
}
$conn->set_charset("utf8");

// Detectar método
$method = $_SERVER["REQUEST_METHOD"];

// ==========================================
// 📘 GET: Listar materias o carreras
// ==========================================
if ($method === "GET") {
    if (isset($_GET["tipo"]) && $_GET["tipo"] === "carreras") {
        $result = $conn->query("SELECT id_Carrera, nombre FROM Carreras");
        $carreras = [];
        while ($row = $result->fetch_assoc()) {
            $carreras[] = $row;
        }
        echo json_encode($carreras);
        exit();
    } else {
        // Listar materias con nombre de carrera
        $query = "SELECT m.id_Materia, m.nombre AS materia, c.nombre AS carrera
                  FROM Materias m
                  LEFT JOIN Carreras c ON m.id_Carrera = c.id_Carrera";
        $result = $conn->query($query);
        $materias = [];
        while ($row = $result->fetch_assoc()) {
            $materias[] = $row;
        }
        echo json_encode($materias);
        exit();
    }
}

// ==========================================
// 📗 POST: Agregar nueva materia
// ==========================================
if ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);
    if (empty($data["nombre"]) || empty($data["id_Carrera"])) {
        echo json_encode(["error" => "Faltan campos obligatorios"]);
        exit();
    }

    $nombre = $data["nombre"];
    $idCarrera = $data["id_Carrera"];
    $idEstado = 1;

    $stmt = $conn->prepare("INSERT INTO Materias (id_Estado, id_Carrera, nombre) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $idEstado, $idCarrera, $nombre);
    if ($stmt->execute()) {
        echo json_encode(["mensaje" => "Materia agregada correctamente"]);
    } else {
        echo json_encode(["error" => "Error al agregar: " . $stmt->error]);
    }
    $stmt->close();
    exit();
}

// ==========================================
// 📙 PUT: Actualizar materia
// ==========================================
if ($method === "PUT") {
    $data = json_decode(file_get_contents("php://input"), true);
    if (empty($data["id_Materia"]) || empty($data["nombre"]) || empty($data["id_Carrera"])) {
        echo json_encode(["error" => "Faltan parámetros"]);
        exit();
    }

    $id = $data["id_Materia"];
    $nombre = $data["nombre"];
    $idCarrera = $data["id_Carrera"];

    $stmt = $conn->prepare("UPDATE Materias SET nombre=?, id_Carrera=? WHERE id_Materia=?");
    $stmt->bind_param("sii", $nombre, $idCarrera, $id);
    if ($stmt->execute()) {
        echo json_encode(["mensaje" => "Materia actualizada correctamente"]);
    } else {
        echo json_encode(["error" => "Error al actualizar: " . $stmt->error]);
    }
    $stmt->close();
    exit();
}

// ==========================================
// 📕 DELETE: Eliminar materia
// ==========================================
if ($method === "DELETE") {
    $data = json_decode(file_get_contents("php://input"), true);
    if (empty($data["id_Materia"])) {
        echo json_encode(["error" => "Falta id_Materia"]);
        exit();
    }

    $id = $data["id_Materia"];
    $stmt = $conn->prepare("DELETE FROM Materias WHERE id_Materia=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(["mensaje" => "Materia eliminada correctamente"]);
    } else {
        echo json_encode(["error" => "Error al eliminar: " . $stmt->error]);
    }
    $stmt->close();
    exit();
}

// Si llega aquí, método no soportado
echo json_encode(["error" => "Método no soportado"]);
$conn->close();
?>
