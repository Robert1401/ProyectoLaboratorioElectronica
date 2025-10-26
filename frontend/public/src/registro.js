document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("registroForm");

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const numeroControl   = document.getElementById("numeroControl").value.trim();
    const nombre          = document.getElementById("nombre").value.trim();
    const apellidoPaterno = document.getElementById("apellidoPaterno").value.trim();
    const apellidoMaterno = document.getElementById("apellidoMaterno").value.trim();
    const carrera         = document.getElementById("carrera").value;
    const clave           = document.getElementById("clave").value.trim();
    const confirmarClave  = document.getElementById("confirmarClave").value.trim();

    if (!numeroControl || !nombre || !apellidoPaterno || !apellidoMaterno || !carrera || !clave || !confirmarClave) {
      mostrarMensaje("⚠️ Por favor llena todos los campos.", "error");
      return;
    }

    // Si usas 8 dígitos para el número de control, valida así:
    if (!/^\d{8}$/.test(numeroControl)) {
      mostrarMensaje("⚠️ El número de control debe tener 8 dígitos.", "error");
      return;
    }

    if (clave !== confirmarClave) {
      mostrarMensaje("❌ Las contraseñas no coinciden.", "error");
      return;
    }

    try {
      const response = await fetch("http://localhost:8000/backend/registro.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ numeroControl, nombre, apellidoPaterno, apellidoMaterno, carrera, clave })
      });

      const data = await response.json();
      mostrarMensaje(data.message, data.success ? "exito" : "error");

      if (data.success) {
        setTimeout(() => {
          window.location.href = "../Login/index.html";
        }, 1500);
      }
    } catch (error) {
      console.error(error);
      mostrarMensaje("❌ Error al conectar con el servidor.", "error");
    }
  });

  function mostrarMensaje(texto, tipo) {
    const msg = document.getElementById("mensaje");
    msg.textContent = texto;
    msg.className = "mensaje " + tipo;
  }
});
