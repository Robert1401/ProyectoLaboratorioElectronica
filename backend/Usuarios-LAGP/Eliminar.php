<?php
include("../Conexion-LAGP.php");

$numeroControl = $conn->real_escape_string($_POST['numeroControl'] ?? '');
$tipo = $conn->real_escape_string($_POST['tipo'] ?? '');

if ($tipo == '1' || $tipo == '2') { // Auxiliar o Alumno
    $tabla = "personas";
    $columna = "numeroControl";
} elseif ($tipo == '3') { // Profesor
    $tabla = "profesores";
    $columna = "id_Profesor";
} else {
    echo "⚠️ Tipo de registro inválido.";
    exit;
}

// Update de borrado lógico
$update = "UPDATE $tabla SET id_Estado = 2 WHERE $columna = '$numeroControl'";

try {
    if ($conn->query($update)) {
        echo "Registro eliminado correctamente ✅";
    } else {
        echo "⚠️ Error al eliminar: " . $conn->error;
    }
} catch (mysqli_sql_exception $e) {
    echo "⚠️ Ocurrió un error al eliminar: " . $e->getMessage();
}

$conn->close();
?>
