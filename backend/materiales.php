<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $seleccion = [];

    foreach ($_POST as $material => $cantidad) {
        if ($cantidad > 0) {
            $seleccion[] = [
                "material" => $material,
                "cantidad" => $cantidad
            ];
        }
    }

    if (count($seleccion) > 0) {
        $file = "selecciones.json";

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
        } else {
            $data = [];
        }

        $data[] = [
            "fecha" => date("Y-m-d H:i:s"),
            "materiales" => $seleccion
        ];

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

        echo "<h2>✅ Selección registrada correctamente</h2>";
        echo "<a href='materiales.html'>Volver</a>";
    } else {
        echo "<h2>⚠️ No seleccionaste ningún material</h2>";
        echo "<a href='materiales.html'>Intentar de nuevo</a>";
    }
}
?>
<?php
session_start();
// Guardar selección en sesión
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $_SESSION['materiales'] = [];
    foreach ($_POST as $material => $cantidad) {
        if ($cantidad > 0) {
            $_SESSION['materiales'][] = [
                "material" => $material,
                "cantidad" => $cantidad
            ];
        }
    }
    // Redirigir a solicitud.php o solicitud.html
    header("Location: solicitud.php");
    exit();
}
?>
