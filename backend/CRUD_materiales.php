<?php
include 'conexion.php';

$accion = $_POST['accion'] ?? '';

if ($accion == 'insertar') {
    $nombre = $_POST['nombre'];
    $clave = $_POST['clave'];
    $cantidad = $_POST['cantidad'];

    $sql = "INSERT INTO materiales (nombre, clave, cantidad) VALUES ('$nombre', '$clave', '$cantidad')";
    if ($conn->query($sql)) {
        echo json_encode(["status" => "ok", "mensaje" => "Registro insertado correctamente"]);
    } else {
        echo json_encode(["status" => "error", "mensaje" => "Error al insertar: " . $conn->error]);
    }
}

elseif ($accion == 'actualizar') {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $clave = $_POST['clave'];
    $cantidad = $_POST['cantidad'];

    $sql = "UPDATE materiales SET nombre='$nombre', clave='$clave', cantidad='$cantidad' WHERE id=$id";
    if ($conn->query($sql)) {
        echo json_encode(["status" => "ok", "mensaje" => "Registro actualizado correctamente"]);
    } else {
        echo json_encode(["status" => "error", "mensaje" => "Error al actualizar: " . $conn->error]);
    }
}

elseif ($accion == 'eliminar') {
    $id = $_POST['id'];
    $sql = "DELETE FROM materiales WHERE id=$id";
    if ($conn->query($sql)) {
        echo json_encode(["status" => "ok", "mensaje" => "Registro eliminado correctamente"]);
    } else {
        echo json_encode(["status" => "error", "mensaje" => "Error al eliminar: " . $conn->error]);
    }
}

$conn->close();
?>
