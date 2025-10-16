<?php
$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "inventario";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    // Devuelve mensaje de error si falla
    die(json_encode(["status" => "error", "mensaje" => "Error al conectar: " . $conn->connect_error]));
} else {
    // Devuelve mensaje de éxito (solo si se accede directamente a conexion.php)
    echo json_encode(["status" => "ok", "mensaje" => "Conectado a la base de datos"]);
}
?>
