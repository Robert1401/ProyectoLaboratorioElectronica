<?php
include("../conexion.php");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
//Datos del formulario
$numeroControl  = $_POST['numeroControl'] ?? '';
$tipo           = $_POST['tipo'] ?? '';
// Contar cuántos auxiliares activos hay
$resultado = $conn->query("SELECT COUNT(*) AS total FROM personas WHERE id_rol = 1 AND id_estado = 1");
$fila = $resultado->fetch_assoc();
$totalAuxiliares = $fila['total'];

//Tratar de eliminar
try {
    $conn->begin_transaction();
    if($tipo==1 && $totalAuxiliares>1){//Auxiliar
        $conn->query("DELETE FROM usuarios WHERE numerocontrol = '$numeroControl'");
        $conn->query("DELETE FROM personas WHERE numerocontrol = '$numeroControl'");
        echo "Eliminado correctamente ✅";
    }else if($tipo==2){//Alumno
        $conn->query("DELETE FROM usuarios WHERE numerocontrol = '$numeroControl'");
        $conn->query("DELETE FROM carrerasalumnos WHERE numerocontrol = '$numeroControl'");
        $conn->query("DELETE FROM personas WHERE numerocontrol = '$numeroControl'");
        echo "Eliminado correctamente ✅";
    }else if($tipo==3){//Profesor
        $conn->query("DELETE FROM profesores WHERE id_profesor = '$numeroControl'");
        echo "Eliminado correctamente ✅";
    }else{
        echo "No se puede eliminar todos los auxiliares ⚠️";
    }
    $conn->commit();
    
} catch (Exception $e) {
    $conn->rollback();
    $conn->begin_transaction();
    if($tipo==1){ //Auxiliar
        $conn->query("UPDATE usuarios SET id_estado = 2 WHERE numerocontrol = '$numeroControl'");
        $conn->query("UPDATE personas SET id_estado = 2 WHERE numerocontrol = '$numeroControl'");
        echo "Desactivado correctamente ✅";
    }else if($tipo==2){//Alumno
        $conn->query("UPDATE usuarios SET id_estado = 2 WHERE numerocontrol = '$numeroControl'");
        $conn->query("UPDATE personas SET id_estado = 2 WHERE numerocontrol = '$numeroControl'");
        echo "Desactivado correctamente ✅";
    }else if($tipo==3){ //Profesor
        $conn->query("UPDATE profesores SET id_estado = 2 WHERE id_profesor = '$numeroControl'");
        echo "Desactivado correctamente ✅";
    }
    $conn->commit();
}
$conn->close();
?>
