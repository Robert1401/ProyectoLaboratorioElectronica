<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Conexión MySQL
$servername = "127.0.0.1";
$username = "root";
$password = "root";
$dbname = "Laboratorio_Electronica";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "❌ Error al conectar con la base de datos: " . $conn->connect_error]);
    exit;
}

// Obtener JSON
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "❌ No se recibieron datos."]);
    exit;
}

// Validar campos
$required = ["nombre","apellidoPaterno","apellidoMaterno","carrera","usuario","clave"];
foreach($required as $field){
    if(empty($data[$field])){
        echo json_encode(["success"=>false,"message"=>"❌ El campo '$field' es obligatorio."]);
        exit;
    }
}

// Sanitizar
$nombre = $conn->real_escape_string($data["nombre"]);
$apellidoPaterno = $conn->real_escape_string($data["apellidoPaterno"]);
$apellidoMaterno = $conn->real_escape_string($data["apellidoMaterno"]);
$carrera = intval($data["carrera"]);
$usuario = $conn->real_escape_string($data["usuario"]);
$clave = password_hash($data["clave"], PASSWORD_BCRYPT);

// Verificar usuario
$stmtCheck = $conn->prepare("SELECT usuario FROM Usuarios WHERE usuario=?");
$stmtCheck->bind_param("s", $usuario);
$stmtCheck->execute();
$resCheck = $stmtCheck->get_result();
if($resCheck->num_rows > 0){
    echo json_encode(["success"=>false,"message"=>"❌ El usuario '$usuario' ya existe."]);
    exit;
}
$stmtCheck->close();

// Insertar alumno
$stmtAlumno = $conn->prepare("INSERT INTO Alumnos (id_Estado, id_Carrera, nombre, apellidoPaterno, apellidoMaterno) VALUES (1, ?, ?, ?, ?)");
$stmtAlumno->bind_param("isss", $carrera, $nombre, $apellidoPaterno, $apellidoMaterno);

if($stmtAlumno->execute()){
    $numeroControl = $conn->insert_id;

    $stmtUsuario = $conn->prepare("INSERT INTO Usuarios (usuario, numeroControl, clave) VALUES (?, ?, ?)");
    $stmtUsuario->bind_param("sis", $usuario, $numeroControl, $clave);

    if($stmtUsuario->execute()){
        echo json_encode(["success"=>true,"message"=>"✅ Registro exitoso."]);
    } else {
        echo json_encode(["success"=>false,"message"=>"❌ Error al crear usuario: ".$stmtUsuario->error]);
    }
    $stmtUsuario->close();
} else {
    echo json_encode(["success"=>false,"message"=>"❌ Error al registrar alumno: ".$stmtAlumno->error]);
}

$stmtAlumno->close();
$conn->close();
?>
