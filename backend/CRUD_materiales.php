<?php
include 'conexion.php';

$accion = $_POST['accion'] ?? '';

switch ($accion) {
    // ======================================================
    // AGREGAR MATERIAL
    // ======================================================
    case 'agregar':
        $clave = trim($_POST['clave']);
        $nombre = trim($_POST['nombre']);
        $cantidad = intval($_POST['cantidad']);

        // Validación de campos vacíos
        if (empty($clave) || empty($nombre) || $cantidad < 0) {
            echo "Error: Todos los campos son obligatorios y la cantidad debe ser positiva.";
            break;
        }

        // 🔍 Verificar si ya existe un material con la misma clave o nombre
        $check = $conn->prepare("SELECT * FROM materiales WHERE clave = ? OR nombre = ?");
        $check->bind_param("ss", $clave, $nombre);
        $check->execute();
        $resultado = $check->get_result();

        if ($resultado->num_rows > 0) {
            echo "Error: Ya existe un material con la misma clave o nombre.";
        } else {
            $stmt = $conn->prepare("INSERT INTO materiales (clave, nombre, cantidad) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $clave, $nombre, $cantidad);
            $stmt->execute();
            echo "Material agregado correctamente.";
        }
        break;

    // ======================================================
    // ACTUALIZAR MATERIAL
    // ======================================================
    case 'actualizar':
        $clave = trim($_POST['clave']);
        $nombre = trim($_POST['nombre']);
        $cantidad = intval($_POST['cantidad']);

        // Validar existencia
        $check = $conn->prepare("SELECT * FROM materiales WHERE clave = ?");
        $check->bind_param("s", $clave);
        $check->execute();
        $resultado = $check->get_result();

        if ($resultado->num_rows === 0) {
            echo "Error: No existe ningún material con esa clave.";
        } else {
            // Verificar que el nuevo nombre no se repita con otro material
            $checkNombre = $conn->prepare("SELECT * FROM materiales WHERE nombre = ? AND clave != ?");
            $checkNombre->bind_param("ss", $nombre, $clave);
            $checkNombre->execute();
            $resNombre = $checkNombre->get_result();

            if ($resNombre->num_rows > 0) {
                echo "Error: Ya existe otro material con ese nombre.";
            } else {
                $stmt = $conn->prepare("UPDATE materiales SET nombre = ?, cantidad = ? WHERE clave = ?");
                $stmt->bind_param("sis", $nombre, $cantidad, $clave);
                $stmt->execute();
                echo "Material actualizado correctamente.";
            }
        }
        break;

    // ======================================================
    // ELIMINAR MATERIAL
    // ======================================================
    case 'eliminar':
        $clave = trim($_POST['clave']);

        $check = $conn->prepare("SELECT * FROM materiales WHERE clave = ?");
        $check->bind_param("s", $clave);
        $check->execute();
        $resultado = $check->get_result();

        if ($resultado->num_rows === 0) {
            echo "Error: No existe un material con esa clave.";
        } else {
            $stmt = $conn->prepare("DELETE FROM materiales WHERE clave = ?");
            $stmt->bind_param("s", $clave);
            $stmt->execute();
            echo "Material eliminado correctamente.";
        }
        break;

    // ======================================================
    // LISTAR MATERIALES
    // ======================================================
    case 'listar':
        $result = $conn->query("SELECT * FROM materiales ORDER BY nombre ASC");
        $datos = [];
        while ($row = $result->fetch_assoc()) {
            $datos[] = $row;
        }
        echo json_encode($datos);
        break;
}

$conn->close();
?>
