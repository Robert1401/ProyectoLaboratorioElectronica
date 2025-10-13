<?php
include("../Conexion-LAGP.php"); // Conexión a la BD

if (isset($_POST['nombreOriginal'], $_POST['nuevoNombre']) && !empty(trim($_POST['nuevoNombre']))) {
    $nombreOriginal = trim($_POST['nombreOriginal']);
    $nuevoNombre = trim($_POST['nuevoNombre']);

    // Evitar inyección SQL
    $nombreOriginal = $conn->real_escape_string($nombreOriginal);
    $nuevoNombre = $conn->real_escape_string($nuevoNombre);

    // Manejar errores sin mostrar detalles del servidor
    try {
        $sql = "UPDATE materias SET nombre='$nuevoNombre' WHERE nombre='$nombreOriginal'";
        $conn->query($sql);
        echo "Materia guardada correctamente ✅";
    } catch (mysqli_sql_exception $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo "⚠️ Esa materia ya existe, ingresa un nombre diferente.";
        } else {
            echo "⚠️ Ocurrió un error al guardar la materia.";
        }
    }

} else {
    echo "Debes seleccionar una materia y escribir un nuevo nombre ❌";
}

$conn->close();
?>
