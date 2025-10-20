<?php
    include("../Conexion-LAGP.php"); // Conexión a la BD

    $id              = $conn->real_escape_string($_POST['id'] ?? '');
    $numeroControl   = $conn->real_escape_string($_POST['numeroControl'] ?? '');
    $nombre          = $conn->real_escape_string($_POST['nombre'] ?? '');
    $apellidoPaterno = $conn->real_escape_string($_POST['apellidoPaterno'] ?? '');
    $apellidoMaterno = $conn->real_escape_string($_POST['apellidoMaterno'] ?? '');
    $carrera         = $conn->real_escape_string($_POST['carrera'] ?? '');

    $insertAlumAux = "INSERT INTO personas (numeroControl, id_Rol, id_Estado, nombre, apellidoPaterno, apellidoMaterno) 
        VALUES ('$numeroControl', '$id', 1, '$nombre', '$apellidoPaterno', '$apellidoMaterno')";

    if($id==1){ //Auxiliar
        try{
            $conn->query($insertAlumAux);
            echo "Auxiliar registrado correctamente ✅";
        } catch (mysqli_sql_exception $e) {

            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "⚠️ Ese Auxiliar ya existe.";
            } else {
                echo "⚠️ Ocurrió un error al guardar.";
            }
        }

    }else if($id==2){ //Alumno
        //Guardar Persona 
        try {
            $conn->query($insertAlumAux);

            // Si todo bien, insertamos la carrera
            $insertCarreraAlumno = "INSERT INTO CarrerasAlumnos (numeroControl, id_Carrera) VALUES ('$numeroControl', '$carrera')";
            $conn->query($insertCarreraAlumno);
            echo "Alumno registrado correctamente ✅";

        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "⚠️ Ese alumno ya existe o ya tiene carrera asignada.";
            } else {
                echo "⚠️ Ocurrió un error al guardar.";
            }
        }     
    }else if($id==3){ //Profesor
        $insertProfesor = "INSERT INTO Profesores (id_Profesor, id_Estado, nombre, apellidoPaterno, apellidoMaterno) 
        VALUES ($numeroControl,1,'$nombre','$apellidoPaterno','$apellidoMaterno')";
        try{
            $conn->query($insertProfesor);
            echo "Profesor registrado correctamente ✅";
        } catch (mysqli_sql_exception $e) {

            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "⚠️ Ese Profesor ya existe.";
            } else {
                echo "⚠️ Ocurrió un error al guardar.";
            }
        }
    }else{
        echo "Ocurrió un error en la selección de un tipo de registro (Alumno, Profesor, Auxiliar) ❌";
    }
    $conn->close();
?>
