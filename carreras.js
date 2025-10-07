document.addEventListener("DOMContentLoaded", () => {
  const nombreInput = document.getElementById("nombre");
  const btnAgregar = document.getElementById("btnAgregar");
  const mensaje = document.getElementById("mensaje");
  const tablaBody = document.querySelector("#tablaCarreras tbody");

  // Función para cargar las carreras desde PHP
  function cargarCarreras() {
    fetch("cargar_carreras.php")
      .then(resp => resp.json())
      .then(data => {
        tablaBody.innerHTML = "";
        data.forEach(carrera => {
          const fila = document.createElement("tr");
          fila.innerHTML = `<td>${carrera.ClaveCarrera}</td><td>${carrera.NombreCarrera}</td>`;
          tablaBody.appendChild(fila);
        });
      });
  }

  cargarCarreras(); // Cargar al iniciar

  // Agregar nueva carrera
  btnAgregar.addEventListener("click", () => {
    const nombre = nombreInput.value.trim();
    if (!nombre) {
      mensaje.textContent = "Ingresa el nombre de la carrera";
      mensaje.style.color = "red";
      return;
    }

    fetch("agregar_carrera.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ nombre })
    })
    .then(resp => resp.json())
    .then(data => {
      mensaje.textContent = data.message;
      mensaje.style.color = data.success ? "green" : "red";
      if (data.success) {
        nombreInput.value = "";
        cargarCarreras();
      }
    });
  });
});

function irInicio() {
  window.location.href = "opciones.html";
}
