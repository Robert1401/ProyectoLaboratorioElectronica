<?php
include("../Conexion-LAGP.php"); // Conexión a la BD

$id              = $conn->real_escape_string($_POST['id'] ?? '');
$numeroControl   = $conn->real_escape_string($_POST['numeroControl'] ?? '');
$nombre          = $conn->real_escape_string($_POST['nombre'] ?? '');
$apellidoPaterno = $conn->real_escape_string($_POST['apellidoPaterno'] ?? '');
$apellidoMaterno = $conn->real_escape_string($_POST['apellidoMaterno'] ?? '');
$carrera         = $conn->real_escape_string($_POST['carrera'] ?? '');

// Determinar tabla y columna según tipo
if($id == 1 || $id == 2){ // Auxiliar o Alumno
    $tabla = "personas";
    $columna = "numeroControl";
} elseif($id == 3){ // Profesor
    $tabla = "profesores";
    $columna = "id_Profesor";
} else {
    echo "❌ Tipo de registro inválido";
    exit;
}

// Verificar si ya existe
$check = $conn->query("SELECT * FROM $tabla WHERE $columna = '$numeroControl'");

if($check->num_rows > 0){
    // Existe → Update
    $update = "UPDATE $tabla SET 
                nombre='$nombre', 
                apellidoPaterno='$apellidoPaterno', 
                apellidoMaterno='$apellidoMaterno', 
                id_Estado=1
               WHERE $columna='$numeroControl'";
    try{
        $conn->query($update);

        // Si es alumno, actualizar carrera
        if($id == 2){
            $conn->query("REPLACE INTO CarrerasAlumnos (numeroControl, id_Carrera) VALUES ('$numeroControl','$carrera')");
        }

        echo "Registro actualizado correctamente ✅";
    } catch (mysqli_sql_exception $e){
        echo "⚠️ Error al actualizar: " . $e->getMessage();
    }

} else {
    // No existe → Insert
    try{
        if($id == 1 || $id == 2){ // Auxiliar o Alumno
            $conn->query("INSERT INTO personas (numeroControl, id_Rol, id_Estado, nombre, apellidoPaterno, apellidoMaterno)
                         VALUES ('$numeroControl','$id',1,'$nombre','$apellidoPaterno','$apellidoMaterno')");
            if($id == 2){
                $conn->query("INSERT INTO CarrerasAlumnos (numeroControl, id_Carrera) VALUES ('$numeroControl','$carrera')");
            }
        } elseif($id == 3){ // Profesor
            $conn->query("INSERT INTO Profesores (id_Profesor, id_Estado, nombre, apellidoPaterno, apellidoMaterno)
                         VALUES ('$numeroControl',1,'$nombre','$apellidoPaterno','$apellidoMaterno')");
        }

        echo "Registro guardado correctamente ✅";

    } catch (mysqli_sql_exception $e){
        echo "⚠️ Error al guardar: " . $e->getMessage();
    }
}

$conn->close();
?>
