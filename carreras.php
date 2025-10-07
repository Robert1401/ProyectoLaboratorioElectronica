<?php

$servername = "127.0.0.1";
$username = "root"; // cámbialo si usas otro usuario
$password = "root";     // pon tu contraseña si tienes
$database = "Laboratorio_Electronica"; // nombre de tu base

$conn = new mysqli($servername, $username, $password, $database);

// Verificar conexión
if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["agregar"])) {
    $nombre = $_POST["nombre"];
    $clave = $_POST["clave"];

    if (!empty($nombre) && !empty($clave)) {
        $sql = "INSERT INTO Carreras (ClaveCarrera, NombreCarrera) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $clave, $nombre);

        if ($stmt->execute()) {
            echo "<script>alert('✅ Carrera agregada correctamente');</script>";
        } else {
            echo "<script>alert('⚠️ Error: Clave repetida o datos inválidos');</script>";
        }
        $stmt->close();
    }
}

// ==========================
// 🗑️ ELIMINAR CARRERA
// ==========================
if (isset($_GET["eliminar"])) {
    $id = $_GET["eliminar"];
    $sql = "DELETE FROM Carreras WHERE ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    echo "<script>alert('🗑️ Carrera eliminada correctamente'); window.location.href='carreras.php';</script>";
}

// ==========================
// 📋 CONSULTAR TODAS LAS CARRERAS
// ==========================
$result = $conn->query("SELECT * FROM Carreras ORDER BY ID ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Carreras</title>
  <link rel="stylesheet" href="carreras.css">
</head>
<body>
  <div class="contenedor">
    <header>
      <img src="logo1.png" alt="Logo TecNM" class="logo izquierda">
      <h1>Carreras</h1>
      <img src="escudo.png" alt="Escudo" class="logo derecha">
    </header>

    <main>
      <section class="formulario">
        <form method="POST" action="">
          <label for="nombre">Nombre:</label>
          <input type="text" id="nombre" name="nombre" required>

          <label for="clave">Clave:</label>
          <input type="text" id="clave" name="clave" required>

          <div class="botones">
            <button type="submit" name="agregar" class="btn rojo">Agregar</button>
          </div>
        </form>
      </section>

      <section class="tabla">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Clave</th>
              <th>Nombre</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($fila = $result->fetch_assoc()) { ?>
              <tr>
                <td><?php echo $fila['ID']; ?></td>
                <td><?php echo $fila['ClaveCarrera']; ?></td>
                <td><?php echo $fila['NombreCarrera']; ?></td>
                <td>
                  <a href="?eliminar=<?php echo $fila['ID']; ?>" onclick="return confirm('¿Seguro que deseas eliminar esta carrera?')">
                    <button class="btn rojo">Eliminar</button>
                  </a>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </section>
    </main>

    <footer>
      <div class="iconos">
        <button class="icono inicio" onclick="irInicio()">&#x2302;</button>
      </div>
    </footer>
  </div>

  <script>
    function irInicio() {
      window.location.href = "auxiliar.html";
    }
  </script>
</body>
</html>
