<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $no_control = $_POST['no_control'];
    $alumno = $_POST['alumno'];
    $semestre = $_POST['semestre'];
    $materia = $_POST['materia'];
    $mesa = $_POST['mesa'];
    $cantidades = $_POST['cant'];
    $descripciones = $_POST['desc'];

    // Crear un arreglo con los materiales
    $materiales = [];
    for ($i = 0; $i < count($cantidades); $i++) {
        if (!empty($cantidades[$i]) && !empty($descripciones[$i])) {
            $materiales[] = [
                "cantidad" => $cantidades[$i],
                "descripcion" => $descripciones[$i]
            ];
        }
    }

    // Guardar en archivo JSON (puede cambiarse por BD)
    $solicitud = [
        "no_control" => $no_control,
        "alumno" => $alumno,
        "semestre" => $semestre,
        "materia" => $materia,
        "mesa" => $mesa,
        "materiales" => $materiales,
        "fecha" => date("Y-m-d H:i:s")
    ];

    $file = "solicitudes.json";
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
    } else {
        $data = [];
    }

    $data[] = $solicitud;
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

    echo "<h2>Solicitud guardada con éxito ✅</h2>";
    echo "<a href='solicitud.html'>Volver</a>";
}
?>

<?php
session_start();

// Datos de usuario de ejemplo (puede venir de login)
$nombre_usuario = $_SESSION['usuario'] ?? "Alumno Ejemplo";
$no_control = $_SESSION['no_control'] ?? "12345678";
$semestre = $_SESSION['semestre'] ?? "1";
$materia = $_SESSION['materia'] ?? "Matemáticas";
$mesa = $_SESSION['mesa'] ?? "Mesa 1";

// Materiales seleccionados en materiales.php
$materialesSeleccionados = $_SESSION['materiales'] ?? [];
?>

