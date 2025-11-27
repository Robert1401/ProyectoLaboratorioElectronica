<?php
include("../conexion.php");

$id              = $conn->real_escape_string($_POST['id'] ?? '');
$numeroControl   = $conn->real_escape_string($_POST['numeroControl'] ?? '');
$nombre          = $conn->real_escape_string($_POST['nombre'] ?? '');
$apellidoPaterno = $conn->real_escape_string($_POST['apellidoPaterno'] ?? '');
$apellidoMaterno = $conn->real_escape_string($_POST['apellidoMaterno'] ?? '');
$carrera         = $conn->real_escape_string($_POST['carrera'] ?? '');
$clave           = $conn->real_escape_string($_POST['clave'] ?? '');

//Buscar si existe en PERSONAS
$stmt = $conn->prepare("SELECT id_Estado FROM personas WHERE numeroControl = ?");
$stmt->bind_param("s", $numeroControl);
$stmt->execute();
$checkPersona = $stmt->get_result();

//Buscar si existe en PROFESORES (solo si no está en personas)
if($checkPersona->num_rows == 0){
    $stmt = $conn->prepare("SELECT id_Estado FROM profesores WHERE id_profesor = ?");
    $stmt->bind_param("s", $numeroControl);
    $stmt->execute();
    $checkProfesor = $stmt->get_result();
} else {
    $checkProfesor = false;
}

// Si no existe en ninguna tabla → Insert
if($checkPersona->num_rows == 0 && ($checkProfesor === false || $checkProfesor->num_rows == 0)){

    try{
        if($id == 1 || $id == 2){ // Auxiliar o Alumno
            $stmt = $conn->prepare(" INSERT INTO personas (numeroControl, id_Rol, id_Estado, nombre, apellidoPaterno, apellidoMaterno) VALUES (?, ?, 1, ?, ?, ?)");
            $stmt->bind_param("sisss", $numeroControl, $id, $nombre, $apellidoPaterno, $apellidoMaterno);
            $stmt->execute();

            $hash = password_hash($clave, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO usuarios (id_Estado, numeroControl, Clave) VALUES ('1',?,?)");
            $stmt->bind_param("is", $numeroControl, $hash);
            $stmt->execute();

            if($id == 2){
                $stmt = $conn->prepare("INSERT INTO CarrerasAlumnos (numeroControl, id_Carrera) VALUES (?, ?)");
                $stmt->bind_param("si", $numeroControl, $carrera);
                $stmt->execute();
            }
            echo "Guardado correctamente ✅";
        } else if($id == 3){ // Profesor
            $stmt = $conn->prepare("INSERT INTO Profesores (id_Profesor, id_Estado, nombre, apellidoPaterno, apellidoMaterno) VALUES (?, 1, ?, ?, ?)");
            $stmt->bind_param("isss", $numeroControl, $nombre, $apellidoPaterno, $apellidoMaterno);
            $stmt->execute();
            echo "Guardado correctamente ✅";
        } else{
            echo "Error al guardar ❌";
        }
    } catch (mysqli_sql_exception $e){
        echo "⚠️ Error al guardar: " . $e->getMessage();
    }

    $conn->close();
    exit;
}

// Si existe → validamos id_estado
if($checkPersona->num_rows > 0){
    $row = $checkPersona->fetch_assoc();
} else {
    $row = $checkProfesor->fetch_assoc();
}

$idEstado = $row['id_Estado'];

if($idEstado == 1){
    echo "⚠️ Advertencia: Usuario Duplicado (ya está activo).";
} elseif($idEstado == 2){
    include("modificar.php"); // Reactivar / modificar usuario
    echo "-> Usuario Activado ⚡";
} else {
    echo "⚠️ Error: Estado de usuario desconocido ($idEstado).";
}
