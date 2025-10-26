const tabla        = document.getElementById("tabla-carreras");
const inputNombre  = document.getElementById("nombre");

const btnGuardar    = document.querySelector(".guardar");
const btnActualizar = document.querySelector(".actualizar");
const btnEliminar   = document.querySelector(".eliminar");

let carreras = [];
let idSeleccionado   = null;   // id de la fila seleccionada (null = modo Nueva)
let nombreOriginal   = "";     // nombre original de la fila seleccionada (para detectar "dirty")

<<<<<<< HEAD
// 🔹 Cargar carreras al inicio
document.addEventListener("DOMContentLoaded", cargarMateriales);

async function cargarMateriales() {
  const res = await fetch("/backend/carreras.php");
  const data = await res.json();
  carreras = data;
  mostrarTabla();
=======
const API = "/backend/carreras.php";

/* ========== Toast ========== */
function showToast(message, type = "info", duration = 2200) {
  const host = document.getElementById("toast");
  if (!host) { alert(message); return; }
  const icons = { success: "✓", error: "✕", info: "ℹ︎", warn:"⚠︎" };
  host.innerHTML = `
    <div class="toast-card ${type}" role="status" aria-live="polite" aria-atomic="true">
      <div class="toast-icon">${icons[type] || "ℹ︎"}</div>
      <div class="toast-text">${message}</div>
    </div>`;
  host.classList.add("show");
  const hide = () => { host.classList.remove("show"); host.innerHTML = ""; };
  const t = setTimeout(hide, duration);
  host.onclick = () => { clearTimeout(t); hide(); };
>>>>>>> 5e57e57cd52febb9c0343cce623d814322ff019d
}

/* ========== Confirm ========== */
function showConfirm(texto) {
  const modal     = document.getElementById("confirm");
  const card      = modal?.querySelector(".confirm-card");
  const label     = document.getElementById("confirm-text");
  const btnOk     = document.getElementById("confirm-aceptar");
  const btnCancel = document.getElementById("confirm-cancelar");

  return new Promise((resolve) => {
    if (!modal || !card || !label || !btnOk || !btnCancel) {
      resolve(window.confirm(texto || "¿Seguro que deseas continuar?"));
      return;
    }
    label.textContent = texto || "¿Seguro que deseas continuar?";
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");

    const onOk      = (e) => { e.stopPropagation(); close(true); };
    const onCancel  = (e) => { e?.stopPropagation?.(); close(false); };
    const onBackdrop= (e) => { if (!card.contains(e.target)) close(false); };
    const onKey     = (e) => { if (e.key === "Escape") close(false); if (e.key === "Enter") close(true); };

    function close(v){
      modal.classList.remove("open");
      modal.setAttribute("aria-hidden","true");
      btnOk.removeEventListener("click",onOk);
      btnCancel.removeEventListener("click",onCancel);
      modal.removeEventListener("click",onBackdrop);
      window.removeEventListener("keydown",onKey);
      resolve(v);
    }
    btnOk.addEventListener("click",onOk,{once:true});
    btnCancel.addEventListener("click",onCancel,{once:true});
    modal.addEventListener("click",onBackdrop);
    window.addEventListener("keydown",onKey);
  });
}

/* ========== Fetch helper ========== */
async function fetchJson(url, options = {}) {
  const res = await fetch(url, options);
  let data; try { data = await res.json(); } catch { data = {}; }
  if (!res.ok) throw new Error(data.mensaje || data.error || `Error (${res.status}).`);
  if (data.error) throw new Error(data.error);
  return data;
}

/* ========== Normalizador para comparar nombres ========== */
const norm = s => (s||"").normalize("NFKC").trim().toLowerCase().replace(/\s+/g," ");

/* ========== Estados de botones (máquina de estados) ========== */
function isDirty(){
  // Cambió el texto respecto al original en modo Edición
  if (idSeleccionado == null) return false;
  return norm(inputNombre.value) !== norm(nombreOriginal);
}

function updateButtonStates(){
  const hayTexto = (inputNombre.value || "").trim().length > 0;

  if (idSeleccionado == null) {
    // Modo NUEVA
    btnGuardar.disabled    = !hayTexto;
    btnActualizar.disabled = true;
    btnEliminar.disabled   = true;
    return;
  }

  // Modo EDICIÓN
  const changed = isDirty();
  btnGuardar.disabled    = true;              // no se guarda en edición
  btnActualizar.disabled = !hayTexto || !changed; // actualizar solo si hay cambios
  btnEliminar.disabled   = changed;           // si hay cambios => bloqueo de eliminar

  // Si está bloqueado por "dirty", avisito en title (opcional)
  btnEliminar.title = changed ? "Tienes cambios sin guardar. Actualiza primero." : "";
}

/* Helpers de selección */
function limpiarSeleccion(){
  [...tabla.querySelectorAll("tr")].forEach(tr => tr.classList.remove("seleccionada"));
  idSeleccionado = null;
  nombreOriginal = "";
  inputNombre.value = "";
  updateButtonStates();
}
function seleccionarFilaVisual(fila){
  [...tabla.querySelectorAll("tr")].forEach(tr => tr.classList.remove("seleccionada"));
  fila?.classList.add("seleccionada");
}

/* ========== Cargar al iniciar ========== */
document.addEventListener("DOMContentLoaded", async () => {
  await cargarCarreras();
  updateButtonStates();
  inputNombre.addEventListener("input", () => {
    // Mostrar “modo escribiendo”
    if (idSeleccionado == null) {
      // Nueva: habilitar Guardar cuando hay texto
      updateButtonStates();
    } else {
      // Edición: si cambias, habilita Actualizar y bloquea Eliminar
      updateButtonStates();
    }
  });
});

async function cargarCarreras() {
  try {
    const data = await fetchJson(API, { method: "GET" });
    carreras = Array.isArray(data) ? data : [];
    mostrarTabla();
  } catch (err) {
    showToast(err.message || "No se pudieron cargar las carreras.", "error", 3000);
  }
}

/* ========== Render tabla ========== */
function mostrarTabla() {
  tabla.innerHTML = "";
  carreras.forEach(c => {
    const tr = document.createElement("tr");
    tr.innerHTML = `<td>${c.nombre}</td>`;
    tr.onclick = () => {
      // Selección -> entrar a modo EDICIÓN
      seleccionarFilaVisual(tr);
      idSeleccionado = c.id_Carrera;
      nombreOriginal = c.nombre;
      inputNombre.value = c.nombre;
      updateButtonStates(); // aquí se habilita Eliminar y (si no cambiaste) Actualizar queda gris
    };
    tabla.appendChild(tr);
  });
}

/* ========== Guardar ========== */
btnGuardar.addEventListener("click", async () => {
  const nombre = (inputNombre.value || "").trim();
  if (!nombre) { showToast("Ingresa un nombre de carrera.", "info"); return; }

  if (carreras.some(c => norm(c.nombre) === norm(nombre))) {
    showToast("❗ Esa carrera ya existe.", "info"); return;
  }

  try {
    const data = await fetchJson(API, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ nombre })
    });

<<<<<<< HEAD
  const data = await res.json();
  alert(data.mensaje || data.error);
  inputNombre.value = "";
  cargarMateriales();
=======
    showToast("✅ Guardado. Ahora puedes actualizar o eliminar.", "success");

    // Añadir en memoria y re-render
    const nuevo = { id_Carrera: data.id_Carrera, nombre: data.nombre || nombre };
    carreras.push(nuevo);
    mostrarTabla();

    // Seleccionar automáticamente la nueva fila -> pasa a modo EDICIÓN
    const filas = [...tabla.querySelectorAll("tr")];
    const idx   = carreras.findIndex(x => x.id_Carrera === nuevo.id_Carrera);
    if (idx !== -1) {
      seleccionarFilaVisual(filas[idx]);
      idSeleccionado = nuevo.id_Carrera;
      nombreOriginal = nuevo.nombre;
      inputNombre.value = nuevo.nombre;
    } else {
      // fallback si no localiza la fila
      idSeleccionado = nuevo.id_Carrera;
      nombreOriginal = nuevo.nombre;
      inputNombre.value = nuevo.nombre;
    }
    updateButtonStates(); // en edición: Eliminar ON, Actualizar OFF (gris)

  } catch (err) {
    showToast(err.message, "error", 3200);
  }
>>>>>>> 5e57e57cd52febb9c0343cce623d814322ff019d
});

/* ========== Actualizar ========== */
btnActualizar.addEventListener("click", async () => {
  if (idSeleccionado == null) { showToast("Selecciona una carrera primero.", "info"); return; }
  const nombre = (inputNombre.value || "").trim();
  if (!nombre) { showToast("Ingresa el nuevo nombre.", "info"); return; }

  if (carreras.some(c => c.id_Carrera !== idSeleccionado && norm(c.nombre) === norm(nombre))) {
    showToast("❗ Ya existe otra carrera con ese nombre.", "info"); return;
  }

  try {
    const data = await fetchJson(API, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id_Carrera: idSeleccionado, nombre })
    });

<<<<<<< HEAD
  const data = await res.json();
  alert(data.mensaje || data.error);
  inputNombre.value = "";
  idSeleccionado = null;
  cargarMateriales();
=======
    // Actualiza memoria y UI
    const idx = carreras.findIndex(x => x.id_Carrera === idSeleccionado);
    if (idx !== -1) carreras[idx].nombre = nombre;
    mostrarTabla();

    showToast("✏️ Actualizada. Volviendo a modo nuevo.", "success");

    // “se quita y se pone todo gris”
    limpiarSeleccion();

  } catch (err) {
    showToast(err.message, "error", 3200);
  }
>>>>>>> 5e57e57cd52febb9c0343cce623d814322ff019d
});

/* ========== Eliminar (con bloqueo si hay cambios) ========== */
btnEliminar.addEventListener("click", async () => {
  if (idSeleccionado == null) { showToast("Selecciona una carrera primero.", "info"); return; }

  if (isDirty()) {
    showToast("No puedes eliminar: estás editando esta carrera. Presiona ACTUALIZAR primero.", "warn", 3200);
    return;
  }

<<<<<<< HEAD
  inputNombre.value = "";
  idSeleccionado = null;
  cargarMateriales();
=======
  // Tomar nombre visible para el mensaje
  const filaSel = [...tabla.querySelectorAll("tr")].find(tr => tr.classList.contains("seleccionada"));
  const nombreSel = filaSel ? filaSel.textContent.trim() : "esta carrera";

  const ok = await showConfirm(`Se borrará la carrera: "${nombreSel}".\n¿Estás seguro?`);
  if (!ok) return;

  try {
    const params = new URLSearchParams({ id_Carrera: String(idSeleccionado) });
    const data = await fetchJson(`${API}?${params.toString()}`, { method: "DELETE" });

    // Quitar de memoria y re-render
    carreras = carreras.filter(c => c.id_Carrera !== idSeleccionado);
    mostrarTabla();

    showToast(data.mensaje || "🗑️ Carrera eliminada", "success");

    // Volver a modo nuevo
    limpiarSeleccion();

  } catch (err) {
    showToast(err.message || "No se pudo eliminar.", "error");
  }
>>>>>>> 5e57e57cd52febb9c0343cce623d814322ff019d
});
