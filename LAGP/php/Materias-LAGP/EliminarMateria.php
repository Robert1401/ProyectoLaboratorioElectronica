<?php
include("../conexion.php"); // Conexión a la BD

if (isset($_POST['nombreOriginal'])) {
    $nombreOriginal = trim($_POST['nombreOriginal']);
    $nombreOriginal = $conn->real_escape_string($nombreOriginal);

    // 1. Obtener el id_materia según el nombre
    $sqlId = "SELECT id_materia FROM materias WHERE nombre = '$nombreOriginal' LIMIT 1";
    $resId = $conn->query($sqlId);

    $row = $resId->fetch_assoc();
    $idMateria = $row['id_materia'];

    // 2. Verificar si la materia está en uso en la tabla prestamos
    $sqlUso = "SELECT COUNT(*) AS total FROM prestamos WHERE id_materia = $idMateria";
    $resUso = $conn->query($sqlUso);
    $rowUso = $resUso->fetch_assoc();

    if ($rowUso['total'] > 0) {

        // 3. Si está en uso → cambiar estado a 2
        $sqlUpdate = "UPDATE materias SET id_estado = 2 WHERE id_materia = $idMateria";
        
        if ($conn->query($sqlUpdate)) {
            echo "Materia desactivada ⚡";
        } else {
            echo "Error al desactivar la materia ❌";
        }

    } else {

        // 4. Si NO está en uso → eliminar
        $sqlDelete = "DELETE FROM materias WHERE id_materia = $idMateria";

        if ($conn->query($sqlDelete)) {
            echo "Materia eliminada correctamente ✅";
        } else {
            echo "Error al eliminar la materia ❌";
        }
    }

} else {
    echo "Debes seleccionar una materia ❌";
}

$conn->close();
?>
