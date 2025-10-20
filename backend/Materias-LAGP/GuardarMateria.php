<?php
include("../Conexion-LAGP.php"); // Conexión a la BD

    /*_POST['nombre'] → obtiene el valor enviado desde un formulario con method="POST".
    Ejemplo: <input name="nombre" ...>

    isset() → verifica que la variable exista y no sea null.

    trim() → quita los espacios en blanco al inicio y final.

    !empty() → comprueba que la cadena no esté vacía ("" o " "). */

if (isset($_POST['nombre']) && !empty(trim($_POST['nombre']))) {
    $nombre = trim($_POST['nombre']);
    $nombre = $conn->real_escape_string($nombre); // Evitar inyección SQL

    // Obtener el id_Materia más alto
    $sqlMax = "SELECT MAX(id_Materia) AS max_id FROM materias";
    $result = $conn->query($sqlMax);

    if ($result) {
        $row = $result->fetch_assoc();
        $idMateria = ($row['max_id'] !== null) ? $row['max_id'] + 1 : 1;
    } else {
        echo "Error al obtener el último id ";
        exit;
    }

    $idEstado = 1; // Siempre 1

    // Manejar errores sin mostrar detalles del servidor
    try {
        $sqlInsert = "INSERT INTO materias (id_Materia, id_Estado, nombre) 
                      VALUES ($idMateria, $idEstado, '$nombre')";
        $conn->query($sqlInsert);
        echo "Materia guardada correctamente ✅";
    } catch (mysqli_sql_exception $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo "⚠️ Esa materia ya existe, ingresa un nombre diferente.";
        } else {
            echo "⚠️ Ocurrió un error al guardar la materia.";
        }
    }
    
} else {
    echo "El campo está vacío ❌";
}

$conn->close();
?>
