<?php
session_start();
if(!isset($_SESSION['usuario'])){
    header("Location: index.html"); // si no hay sesión, vuelve al login
    exit();
}
$nombre_usuario = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Laboratorio - Alumnos</title>
  <link rel="stylesheet" href="alumnos.css">
</head>
<body>
  <!-- BARRA SUPERIOR -->
  <header class="topbar">
    <div class="user-info">
      <img src="User.png" alt="Usuario" class="user-img">
      <span class="user-name"><?php echo htmlspecialchars($nombre_usuario); ?></span>
    </div>
  </header>

  <main class="dashboard">
  <h1>Bienvenido al Laboratorio</h1>
  <div class="cards-container">
    <!-- Solicitud de materiales -->
    <div class="card">
  <img src="Solicitud de Materiales.png" alt="Solicitud de materiales">
  <h2>Solicitud de Materiales</h2>
  <p>Solicita los materiales que necesitas para tus prácticas.</p>
  <button onclick="window.location.href='materiales.html'">Ir</button>
</div>


    <!-- Devolución de materiales -->
    <div class="card">
      <img src="devolucion.png" alt="Devolución de materiales">
      <h2>Devolución de Materiales</h2>
      <p>Devuelve los materiales prestados al laboratorio.</p>
      <button>Ir</button>
    </div>

    <!-- Solicitud de asesorías -->
    <div class="card">
      <img src="Solicitud de asesorias.png" alt="Solicitud de asesorías">
      <h2>Solicitud de Asesorías</h2>
      <p>Pide una asesoría para resolver dudas de tus prácticas.</p>
      <button>Ir</button>
    </div>

    <!-- Gestión de asesorías -->
    <div class="card">
      <img src="Gestion de Asesorias.png" alt="Gestión de asesorías">
      <h2>Gestión de Asesorías</h2>
      <p>Revisa las asesorías programadas y pendientes.</p>
      <button>Ir</button>
    </div>
  </div>
</main>

</body>
</html>
