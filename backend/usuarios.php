<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

// Conexión a BD
$host = "127.0.0.1";
$user = "root";
$pass = "root";
$db = "Laboratorio_Electronica";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(["error" => "Error BD: ".$conn->connect_error]);
    exit();
}
$conn->set_charset("utf8mb4");

$method = $_SERVER["REQUEST_METHOD"];

// ================= GET =================
if ($method === "GET") {

    // 🔹 Roles
    if(isset($_GET['roles'])) {
        $roles = [['rol'=>'Alumno'], ['rol'=>'Auxiliar']];
        echo json_encode($roles);
        exit();
    }

    // 🔹 Carreras
    if(isset($_GET['carreras'])) {
        $res = $conn->query("SELECT id_Carrera, nombre FROM Carreras");
        $carreras = [];
        while($row = $res->fetch_assoc()) $carreras[] = $row;
        echo json_encode($carreras);
        exit();
    }

    // 🔹 Buscar usuario por número
    if(isset($_GET['num'])) {
        $num = $_GET['num'];

        // Alumno
        $stmt = $conn->prepare("SELECT u.usuario, u.clave, a.numeroControl AS numero, a.nombre, a.apellidoPaterno, a.apellidoMaterno, a.id_Carrera, 'Alumno' AS rol
                                FROM Usuarios u
                                LEFT JOIN Alumnos a ON u.numeroControl = a.numeroControl
                                WHERE a.numeroControl=?");
        $stmt->bind_param("s", $num);
        $stmt->execute();
        $res = $stmt->get_result();
        if($res->num_rows > 0) {
            echo json_encode($res->fetch_assoc());
            exit();
        }

        // Auxiliar
        $stmt = $conn->prepare("SELECT u.usuario, u.clave, ax.numeroTrabajador AS numero, ax.nombre, ax.apellidoPaterno, ax.apellidoMaterno, NULL AS id_Carrera, 'Auxiliar' AS rol
                                FROM Usuarios u
                                LEFT JOIN Auxiliares ax ON u.numeroTrabajador = ax.numeroTrabajador
                                WHERE ax.numeroTrabajador=?");
        $stmt->bind_param("s", $num);
        $stmt->execute();
        $res = $stmt->get_result();
        if($res->num_rows > 0) {
            echo json_encode($res->fetch_assoc());
            exit();
        }

        echo json_encode(["error"=>"Usuario no encontrado"]);
        exit();
    }

    echo json_encode(["error"=>"Faltan parámetros"]);
    exit();
}

// ================= POST =================
if ($method === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    $usuario = $data["usuario"] ?? null;
    $clave = $data["clave"] ?? null;
    $rol = $data["rol"] ?? null;

    if(!$usuario || !$clave || !$rol) {
        echo json_encode(["error"=>"Faltan campos"]);
        exit();
    }

    $nombre = $data["nombre"] ?? null;
    $apellidoPaterno = $data["apellidoPaterno"] ?? null;
    $apellidoMaterno = $data["apellidoMaterno"] ?? null;
    $id_Carrera = $data["id_Carrera"] ?? null;
    $numControl = $data["numControl"] ?? null;
    $numTrabajador = $data["numTrabajador"] ?? null;
    $claveHash = password_hash($clave, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("INSERT INTO Usuarios (usuario, numeroControl, numeroTrabajador, clave) VALUES (?,?,?,?)");
    $stmt->bind_param("siis", $usuario, $numControl, $numTrabajador, $claveHash);

    echo $stmt->execute() ? json_encode(["mensaje"=>"Usuario registrado ✅"]) : json_encode(["error"=>"Error: ".$stmt->error]);
    exit();
}

// ================= PUT =================
if ($method === "PUT") {
    $data = json_decode(file_get_contents("php://input"), true);
    $usuario = $data["usuario"] ?? null;
    $clave = $data["clave"] ?? null;

    if(!$usuario || !$clave) {
        echo json_encode(["error"=>"Faltan datos"]);
        exit();
    }

    $claveHash = password_hash($clave,PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE Usuarios SET clave=? WHERE usuario=?");
    $stmt->bind_param("ss", $claveHash, $usuario);
    $stmt->execute();

    echo $stmt->affected_rows > 0 ? json_encode(["mensaje"=>"Contraseña actualizada ✅"]) : json_encode(["error"=>"Usuario no existe"]);
    exit();
}

// ================= DELETE =================
if ($method === "DELETE") {
    $data = json_decode(file_get_contents("php://input"), true);
    $usuario = $data["usuario"] ?? null;

    if(!$usuario) {
        echo json_encode(["error"=>"Falta usuario"]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM Usuarios WHERE usuario=?");
    $stmt->bind_param("s", $usuario);
    $stmt->execute();

    echo $stmt->affected_rows > 0 ? json_encode(["mensaje"=>"Usuario eliminado 🗑️"]) : json_encode(["error"=>"Usuario no existe"]);
    exit();
}

echo json_encode(["error"=>"Método no soportado"]);
$conn->close();
?>
