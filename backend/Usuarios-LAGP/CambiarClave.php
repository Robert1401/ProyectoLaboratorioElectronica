<?php
include("../Conexion-LAGP.php");

$numeroControl = $_POST['numeroControl'] ?? '';
$clave = $_POST['clave'] ?? '';

if (empty($numeroControl) || empty($clave)) {
    echo "⚠️ Faltan datos";
    exit;
}

// Hashear la contraseña
$hash = password_hash($clave, PASSWORD_BCRYPT);

// Verificar si el usuario existe
$check = $conn->prepare("SELECT 1 FROM Usuarios WHERE numeroControl = ?");
$check->bind_param("i", $numeroControl);
$check->execute();
$exists = $check->get_result()->num_rows > 0;
$check->close();

if ($exists) {
    // Update existente
    $update = $conn->prepare("UPDATE Usuarios SET Clave = ?, id_Estado = 1 WHERE numeroControl = ?");
    $update->bind_param("si", $hash, $numeroControl);
    if ($update->execute()) {
        echo "Contraseña actualizada correctamente ✅";
    } else {
        echo "⚠️ Error al actualizar la contraseña.";
    }
    $update->close();
} else {
    // Insert nuevo
    $insert = $conn->prepare("INSERT INTO Usuarios (id_Estado, numeroControl, Clave) VALUES (1, ?, ?)");
    $insert->bind_param("is", $numeroControl, $hash);
    if ($insert->execute()) {
        echo "Usuario creado y contraseña asignada ✅";
    } else {
        echo "⚠️ Error al crear el usuario.";
    }
    $insert->close();
}

$conn->close();
?>
