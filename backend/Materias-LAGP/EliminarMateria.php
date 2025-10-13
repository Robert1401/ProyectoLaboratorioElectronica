<?php
include("../Conexion-LAGP.php"); // Conexión a la BD

if (isset($_POST['nombreOriginal'])) {
    $nombreOriginal = trim($_POST['nombreOriginal']);

    // Evitar inyección SQL
    $nombreOriginal = $conn->real_escape_string($nombreOriginal);

    $sql = "DELETE FROM materias WHERE nombre='$nombreOriginal'";

    if ($conn->query($sql)) {
        echo "Materia eliminada correctamente ✅";
    } else {
        echo "Error al eliminar la materia";
    }
} else {
    echo "Debes seleccionar una materia s❌";
}

$conn->close();
?>
