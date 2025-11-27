<?php
include("../conexion.php"); // Conexión a la BD

$id              = $conn->real_escape_string($_POST['id'] ?? '');
$numeroControl   = $conn->real_escape_string($_POST['numeroControl'] ?? '');
$nombre          = $conn->real_escape_string($_POST['nombre'] ?? '');
$apellidoPaterno = $conn->real_escape_string($_POST['apellidoPaterno'] ?? '');
$apellidoMaterno = $conn->real_escape_string($_POST['apellidoMaterno'] ?? '');
$carrera         = $conn->real_escape_string($_POST['carrera'] ?? '');
$clave           = $conn->real_escape_string($_POST['clave'] ?? '');

if ($id == 1) { // AUXILIAR
    try{
        $update = "UPDATE Personas 
                   SET nombre='$nombre', apellidoPaterno='$apellidoPaterno', apellidoMaterno='$apellidoMaterno', id_estado = '1'
                   WHERE numeroControl='$numeroControl' AND id_Rol=$id";
        $conn->query($update);
        //Usuarios
        if($clave !== ''){
            $hash = password_hash($clave, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE Usuarios SET id_estado = '1', clave=? WHERE numeroControl=?");
            $stmt->bind_param("si", $hash, $numeroControl); // El primer parámetro es numérico (i), el segundo string (s)
            $stmt->execute();
        }
        echo "✅ Proceso terminado ";

    }catch(Exception $e){
        echo "❌ Error al modificar Auxiliar: ";
    }
} elseif ($id == 2) { // ALUMNO
    try {
        $updatePersona = "UPDATE Personas 
                          SET nombre='$nombre', apellidoPaterno='$apellidoPaterno', apellidoMaterno='$apellidoMaterno', id_estado = '1'
                          WHERE numeroControl='$numeroControl' AND id_Rol=$id";
        $updateCarrera = "UPDATE CarrerasAlumnos 
                          SET id_Carrera='$carrera'
                          WHERE numeroControl='$numeroControl'";

        $conn->query($updatePersona);
        $conn->query($updateCarrera);
        //Usuarios
        if($clave !== ''){
            $hash = password_hash($clave, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE Usuarios SET id_estado = '1', clave=? WHERE numeroControl=?");
            $stmt->bind_param("si", $hash, $numeroControl); // El primer parámetro es numérico (i), el segundo string (s)
            $stmt->execute();
        }
        echo "✅ Proceso terminado ";
    } catch (Exception $e) {
        echo "❌ Error al modificar alumno: ";
    }

} elseif ($id == 3) { // PROFESOR
    $update = "UPDATE Profesores 
               SET nombre='$nombre', apellidoPaterno='$apellidoPaterno', apellidoMaterno='$apellidoMaterno', id_estado = '1'
               WHERE id_Profesor='$numeroControl'";
    if ($conn->query($update)) {
        echo "✅ Proceso terminado ";
    } else {
        echo "❌ Error al modificar el profesor.";
    }

} else {
    echo "❌ Tipo de registro no reconocido.";
}

$conn->close();
?>
