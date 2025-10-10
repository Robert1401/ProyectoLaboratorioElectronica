<?php
header("Content-Type: application/json");
$servername = "127.0.0.1";
$username = "root";
$password = "roblox12";
$dbname = "Laboratorio_Electronica";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT ClaveCarrera, NombreCarrera FROM Carreras ORDER BY ClaveCarrera";
$result = $conn->query($sql);

$carreras = [];
while($row = $result->fetch_assoc()) {
    $carreras[] = $row;
}

echo json_encode($carreras);
$conn->close();
?>
