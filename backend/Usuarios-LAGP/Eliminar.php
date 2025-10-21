<?php
include("../Conexion-LAGP.php");

$numeroControl = $_POST['numeroControl'] ?? '';
$tipo = $_POST['tipo'] ?? '';

if (empty($numeroControl) || empty($tipo)) {
    echo "⚠️ Datos incompletos.";
    exit;
}

// Determinar tabla y columna (valores controlados)
if ($tipo === '1' || $tipo === '2') { // Auxiliar o Alumno
    $tabla = "personas";
    $columna = "numeroControl";
} elseif ($tipo === '3') { // Profesor
    $tabla = "profesores";
    $columna = "id_Profesor";
} else {
    echo "⚠️ Tipo de registro inválido.";
    exit;
}

try {
    // 1) Verificar si el numeroControl existe en Usuarios
    $stmtExist = $conn->prepare("SELECT 1 FROM Usuarios WHERE numeroControl = ?");
    $stmtExist->bind_param("i", $numeroControl);
    $stmtExist->execute();
    $resExist = $stmtExist->get_result();
    $existsInUsuarios = ($resExist->num_rows > 0);
    $stmtExist->close();

    if ($existsInUsuarios) {
        // 2) Contar cuantos usuarios hay en total
        $stmtCount = $conn->prepare("SELECT COUNT(*) AS total FROM Usuarios");
        $stmtCount->execute();
        $resCount = $stmtCount->get_result();
        $row = $resCount->fetch_assoc();
        $totalUsuarios = (int)$row['total'];
        $stmtCount->close();

        // 3) Si es el último usuario, no permitir eliminar
        if ($totalUsuarios <= 1) {
            echo "⚠️ No se puede eliminar: es el último usuario registrado en la tabla Usuarios.";
            $conn->close();
            exit;
        }
    }

    // 4) Iniciar transacción
    $conn->begin_transaction();

    // 5) Si existe en Usuarios: eliminarlo
    if ($existsInUsuarios) {
        $deleteUsuarios = $conn->prepare("DELETE FROM Usuarios WHERE numeroControl = ?");
        $deleteUsuarios->bind_param("i", $numeroControl);
        if (!$deleteUsuarios->execute()) {
            throw new Exception("Error al eliminar en Usuarios: " . $deleteUsuarios->error);
        }
        $deleteUsuarios->close();
    }

    // 6) Actualizar id_Estado = 2 en la tabla correspondiente (personas o profesores)
    // Nota: $tabla y $columna están validados arriba; se usan directamente en la consulta.
    $updateSql = "UPDATE $tabla SET id_Estado = 2 WHERE $columna = ?";
    $update = $conn->prepare($updateSql);
    $update->bind_param("i", $numeroControl);
    if (!$update->execute()) {
        throw new Exception("Error al actualizar estado en $tabla: " . $update->error);
    }
    $update->close();

    // 7) Commit
    $conn->commit();

    echo "✅ Operación completada: borrado lógico en $tabla";
} catch (Exception $e) {
    // Rollback en caso de error
    if ($conn->errno) {
        $conn->rollback();
    }
    echo "⚠️ Ocurrió un error: " . $e->getMessage();
}

$conn->close();
?>
