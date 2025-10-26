<?php
include("../Conexion-LAGP.php"); // Conexión a la BD

$id              = $conn->real_escape_string($_POST['id'] ?? '');
$numeroControl   = $conn->real_escape_string($_POST['numeroControl'] ?? '');
$nombre          = $conn->real_escape_string($_POST['nombre'] ?? '');
$apellidoPaterno = $conn->real_escape_string($_POST['apellidoPaterno'] ?? '');
$apellidoMaterno = $conn->real_escape_string($_POST['apellidoMaterno'] ?? '');
$carrera         = $conn->real_escape_string($_POST['carrera'] ?? '');

if ($id == 1) { // AUXILIAR
    $update = "UPDATE Personas 
               SET nombre='$nombre', apellidoPaterno='$apellidoPaterno', apellidoMaterno='$apellidoMaterno'
               WHERE numeroControl='$numeroControl' AND id_Rol=$id";
    if ($conn->query($update)) {
        echo "✅ Auxiliar modificado correctamente.";
    } else {
        echo "❌ Error al modificar el auxiliar.";
    }

} elseif ($id == 2) { // ALUMNO
    $updatePersona = "UPDATE Personas 
                      SET nombre='$nombre', apellidoPaterno='$apellidoPaterno', apellidoMaterno='$apellidoMaterno'
                      WHERE numeroControl='$numeroControl' AND id_Rol=$id";
    $updateCarrera = "UPDATE CarrerasAlumnos 
                      SET id_Carrera='$carrera'
                      WHERE numeroControl='$numeroControl'";
    try {
        $conn->query($updatePersona);
        $conn->query($updateCarrera);
        echo "✅ Alumno modificado correctamente.";
    } catch (Exception $e) {
        echo "❌ Error al modificar alumno: " . $e->getMessage();
    }

} elseif ($id == 3) { // PROFESOR
    $update = "UPDATE Profesores 
               SET nombre='$nombre', apellidoPaterno='$apellidoPaterno', apellidoMaterno='$apellidoMaterno'
               WHERE id_Profesor='$numeroControl'";
    if ($conn->query($update)) {
        echo "✅ Profesor modificado correctamente.";
    } else {
        echo "❌ Error al modificar el profesor.";
    }

} else {
    echo "❌ Tipo de registro no reconocido.";
}

$conn->close();
?>
