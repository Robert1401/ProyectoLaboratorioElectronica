<?php
include("../conexion.php"); // Conexión a la BD

if (isset($_POST['nombre']) && !empty(trim($_POST['nombre']))) {

    $nombre = trim($_POST['nombre']);
    $nombre = $conn->real_escape_string($nombre);

    /** 1. Verificar si la materia ya existe **/
    $sqlCheck = "SELECT id_Materia, id_Estado FROM materias WHERE nombre = '$nombre' LIMIT 1";
    $resultCheck = $conn->query($sqlCheck);

    if ($resultCheck && $resultCheck->num_rows > 0) {
        // Ya existe
        $row = $resultCheck->fetch_assoc();
        $idMateriaExistente = $row['id_Materia'];
        $estadoExistente = $row['id_Estado'];

        // Caso 1: Existe y está activa (1)
        if ($estadoExistente == 1) {
            echo "⚠️ Esa materia ya existe, ingresa un nombre diferente.";
            exit;
        }

        // Caso 2: Existe pero está inactiva (2) → Activarla
        if ($estadoExistente == 2) {
            $sqlActivate = "UPDATE materias SET id_Estado = 1 WHERE id_Materia = $idMateriaExistente";
            if ($conn->query($sqlActivate)) {
                echo "Materia Activada ⚡";
            } else {
                echo "⚠️ Error al activar la materia.";
            }
            exit;
        }
    }

    /** 2. Si no existe, insertarla **/

    // Obtener id_Materia más alto
    $sqlMax = "SELECT MAX(id_Materia) AS max_id FROM materias";
    $resultMax = $conn->query($sqlMax);

    if ($resultMax) {
        $rowMax = $resultMax->fetch_assoc();
        $idMateria = ($rowMax['max_id'] !== null) ? $rowMax['max_id'] + 1 : 1;
    } else {
        $idMateria = 1;
    }

    $idEstado = 1; // Siempre 1 en nuevas materias

    try {
        $sqlInsert = "INSERT INTO materias (id_Materia, id_Estado, nombre) 
                      VALUES ($idMateria, $idEstado, '$nombre')";
        $conn->query($sqlInsert);
        echo "Materia guardada correctamente ✅";
    } catch (mysqli_sql_exception $e) {
        echo "⚠️ Ocurrió un error al guardar la materia.";
    }

} else {
    echo "El campo está vacío ❌";
}

$conn->close();
?>
