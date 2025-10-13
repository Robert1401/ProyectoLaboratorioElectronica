const tabla = document.getElementById("tabla-carreras");
const inputNombre = document.getElementById("nombre");
let carreras = [];
let idSeleccionado = null;

// 🔹 Cargar carreras al inicio
document.addEventListener("DOMContentLoaded", cargarCarreras);

async function cargarCarreras() {
  const res = await fetch("/backend/carreras.php");
  const data = await res.json();
  carreras = data;
  mostrarTabla();
}

// 🔹 Mostrar tabla
function mostrarTabla() {
  tabla.innerHTML = "";
  carreras.forEach(c => {
    const fila = document.createElement("tr");
    fila.innerHTML = `<td>${c.nombre}</td>`;
    fila.onclick = () => seleccionarCarrera(c);
    tabla.appendChild(fila);
  });
}

// 🔹 Seleccionar carrera
function seleccionarCarrera(c) {
  inputNombre.value = c.nombre;
  idSeleccionado = c.id_Carrera;
}

// 🔹 Guardar nueva carrera
document.querySelector(".guardar").addEventListener("click", async () => {
  const nombre = inputNombre.value.trim();
  if (!nombre) return alert("Ingresa un nombre de carrera.");

  const res = await fetch("/backend/carreras.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ nombre })
  });

  const data = await res.json();
  alert(data.mensaje || data.error);
  inputNombre.value = "";
  cargarCarreras();
});

// 🔹 Eliminar carrera
document.querySelector(".eliminar").addEventListener("click", async () => {
  if (!idSeleccionado) return alert("Selecciona una carrera primero.");

  if (!confirm("¿Seguro que deseas eliminar esta carrera?")) return;

  const res = await fetch("/backend/carreras.php", {
    method: "DELETE",
    body: `id_Carrera=${idSeleccionado}`
  });

  const data = await res.json();
  alert(data.mensaje || data.error);
  inputNombre.value = "";
  idSeleccionado = null;
  cargarCarreras();
});

// 🔹 Actualizar (por simplicidad, elimina + agrega)
document.querySelector(".actualizar").addEventListener("click", async () => {
  if (!idSeleccionado) return alert("Selecciona una carrera primero.");
  const nombre = inputNombre.value.trim();
  if (!nombre) return alert("Ingresa el nuevo nombre.");

  // Eliminar y volver a agregar
  await fetch("/backend/carreras.php", { method: "DELETE", body: `id_Carrera=${idSeleccionado}` });
  await fetch("/backend/carreras.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ nombre })
  });

  inputNombre.value = "";
  idSeleccionado = null;
  cargarCarreras();
});
