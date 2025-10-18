// Inicializa Flatpickr con tema y traducción
flatpickr("#fecha", {
  dateFormat: "Y-m-d",         // formato para guardar
  altInput: true,              // muestra el formato bonito
  altFormat: "d 'de' F, Y",    // formato visual (ej. 18 de octubre, 2025)
  locale: "es",                // idioma español
  minDate: "today",            // evita fechas pasadas
  disableMobile: "true"        // fuerza el calendario también en móviles
});

const form = document.getElementById("formAsesoria");
const btnAgregar = document.getElementById("btnAgregar");
const btnModificar = document.getElementById("btnModificar");

// Agregar asesoría
btnAgregar.addEventListener("click", () => {
  const datos = new FormData(form);
  datos.append("accion", "agregar");

  fetch("asesoria.php", {
    method: "POST",
    body: datos
  })
  .then(res => res.json())
  .then(data => {
    alert(data.mensaje);
    if (data.estado === "ok") form.reset();
  })
  .catch(() => alert("Error al conectar con el servidor."));
});

// Modificar asesoría
btnModificar.addEventListener("click", () => {
  const datos = new FormData(form);
  datos.append("accion", "modificar");

  fetch("asesoria.php", {
    method: "POST",
    body: datos
  })
  .then(res => res.json())
  .then(data => alert(data.mensaje))
  .catch(() => alert("Error al conectar con el servidor."));
});

