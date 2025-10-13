document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("registroForm");

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const nombre = document.getElementById("nombre").value.trim();
    const apellidoPaterno = document.getElementById("apellidoPaterno").value.trim();
    const apellidoMaterno = document.getElementById("apellidoMaterno").value.trim();
    const carrera = document.getElementById("carrera").value;
    const usuario = document.getElementById("usuario").value.trim();
    const clave = document.getElementById("clave").value.trim();
    const confirmarClave = document.getElementById("confirmarClave").value.trim();

    if (!nombre || !apellidoPaterno || !apellidoMaterno || !carrera || !usuario || !clave) {
      mostrarMensaje("⚠️ Por favor llena todos los campos.", "error");
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
        body: JSON.stringify({ nombre, apellidoPaterno, apellidoMaterno, carrera, usuario, clave })
      });

      const data = await response.json();
      mostrarMensaje(data.message, data.success ? "exito" : "error");

      if (data.success) {
        setTimeout(() => {
          window.location.href = "../Login/index.html";
        }, 2000);
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
