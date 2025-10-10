<?php
include("../Conexion.php");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materia</title>
    <link rel="stylesheet" href="Materia.css">
</head>
<body>
    <div id="principal">
        <div id="encabezado">
            <img src="ITS.png" alt="Logo de Instituto Tecnológico de Saltillo">
            <h1>Agregar Materias</h1>
            <img src="TECNM.png" alt="Logo de Tecnológico Nacional  de México">
        </div>
        <div id="herramientas">
            <div id="campos">
                <h2>Nombre: </h2>
                <input type="text" placeholder="Ingresar ...">
            </div>
            <div id="tabla">
                <table>
                    <tr>
                        <th>Nombre</th>
                    </tr>
                    <?php
                    $sql = "SELECT nombre FROM materias";
                    $resultado = $conexion->query($sql);

                    if ($resultado->num_rows > 0) {
                        while ($fila = $resultado->fetch_assoc()) {
                            echo "<tr><td style='text-align:center; padding:10px;'>" . htmlspecialchars($fila['nombre']) . "</td></tr>";
                        }
                    } else {
                        echo "<tr><td style='text-align:center; padding:10px;'>No hay materias registradas</td></tr>";
                    }

                    $conexion->close();
                    ?>
                </table>
            </div>
        </div>
        <div id="botones">
            <button>Guardar</button>
            <button>Actualizar</button>
            <button>Eliminar</button>
            <button>Atrás</button>
        </div>
    </div>
</body>
</html>