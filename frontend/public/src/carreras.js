const tabla = document.getElementById("tabla-carreras");
const inputNombre = document.getElementById("nombre");
let carreras = [];
let idSeleccionado = null;

// Apunta a tu backend (ruta relativa evita mostrar host/puerto)
const API = "/backend/carreras.php";

// ========== Toast bonito ==========
function showToast(message, type = "info", duration = 2200) {
  const host = document.getElementById("toast");
  if (!host) { alert(message); return; }

  const icons = { success: "✓", error: "✕", info: "ℹ︎" };
  host.innerHTML = `
    <div class="toast-card ${type}" role="status" aria-live="polite" aria-atomic="true">
      <div class="toast-icon">${icons[type] || "ℹ︎"}</div>
      <div class="toast-text">${message}</div>
    </div>`;
  host.classList.add("show");
  const hide = () => { host.classList.remove("show"); host.innerHTML = ""; };
  const t = setTimeout(hide, duration);
  host.onclick = () => { clearTimeout(t); hide(); };
}

// ========== Confirm robusto ==========
function showConfirm(texto) {
  const modal     = document.getElementById("confirm");
  const card      = modal?.querySelector(".confirm-card");
  const label     = document.getElementById("confirm-text");
  const btnOk     = document.getElementById("confirm-aceptar");
  const btnCancel = document.getElementById("confirm-cancelar");

  return new Promise((resolve) => {
    // Fallback si no existe el modal en el HTML
    if (!modal || !card || !label || !btnOk || !btnCancel) {
      const ok = window.confirm(texto || "¿Seguro que deseas continuar?");
      resolve(ok);
      return;
    }

    label.textContent = texto || "¿Seguro que deseas continuar?";
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");

    const onOk      = (e) => { e.stopPropagation(); close(true); };
    const onCancel  = (e) => { e?.stopPropagation?.(); close(false); };
    const onBackdrop= (e) => { if (!card.contains(e.target)) close(false); };
    const onKey     = (e) => { if (e.key === "Escape") close(false); if (e.key === "Enter") close(true); };

    function close(v) {
      modal.classList.remove("open");
      modal.setAttribute("aria-hidden", "true");
      btnOk.removeEventListener("click", onOk);
      btnCancel.removeEventListener("click", onCancel);
      modal.removeEventListener("click", onBackdrop);
      window.removeEventListener("keydown", onKey);
      resolve(v);
    }

    btnOk.addEventListener("click", onOk, { once: true });
    btnCancel.addEventListener("click", onCancel, { once: true });
    modal.addEventListener("click", onBackdrop);
    window.addEventListener("keydown", onKey);
  });
}

// ========== Helper fetch JSON ==========
async function fetchJson(url, options = {}) {
  const res = await fetch(url, options);
  let data;
  try { data = await res.json(); } catch { data = {}; }

  if (!res.ok) {
    const msg = data.mensaje || data.error || `Ocurrió un error (${res.status}).`;
    throw new Error(msg);
  }
  if (data.error) throw new Error(data.error);
  return data;
}

// ========== Cargar al iniciar ==========
document.addEventListener("DOMContentLoaded", cargarCarreras);

async function cargarCarreras() {
  try {
    const data = await fetchJson(API, { method: "GET" });
    carreras = Array.isArray(data) ? data : [];
    mostrarTabla();
  } catch (err) {
    showToast(err.message || "No se pudieron cargar las carreras.", "error", 3000);
  }
}

// ========== Render tabla ==========
function mostrarTabla() {
  tabla.innerHTML = "";
  carreras.forEach(c => {
    const tr = document.createElement("tr");
    tr.innerHTML = `<td>${c.nombre}</td>`;
    tr.onclick = () => seleccionarCarrera(c, tr);
    tabla.appendChild(tr);
  });
}

function seleccionarCarrera(c, fila) {
  [...tabla.querySelectorAll("tr")].forEach(tr => tr.classList.remove("seleccionada"));
  fila?.classList.add("seleccionada");
  inputNombre.value = c.nombre; // autocompleta
  idSeleccionado = c.id_Carrera;
}

// ========== Guardar ==========
document.querySelector(".guardar").addEventListener("click", async () => {
  const nombre = (inputNombre.value || "").trim();
  if (!nombre) { showToast("Ingresa un nombre de carrera.", "info"); return; }

  // Evitar duplicados en cliente (insensible a mayúsculas/espacios)
  const norm = s => s.normalize("NFKC").trim().toLowerCase();
  const yaExiste = carreras.some(c => norm(c.nombre) === norm(nombre));
  if (yaExiste) { showToast("❗ Esa carrera ya existe.", "info"); return; }

  try {
    const data = await fetchJson(API, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ nombre })
    });

    showToast(data.mensaje || "✅ Carrera guardada", "success");

    // Optimista: agrega sin recargar o vuelve a pedir lista
    if (data.id_Carrera) {
      carreras.push({ id_Carrera: data.id_Carrera, nombre: data.nombre || nombre });
      mostrarTabla();
    } else {
      await cargarCarreras();
    }

    inputNombre.value = "";
    idSeleccionado = null;

  } catch (err) {
    showToast(err.message, "error", 3200);
  }
});

// ========== Editar ==========
document.querySelector(".actualizar").addEventListener("click", async () => {
  if (!idSeleccionado) { showToast("Selecciona una carrera primero.", "info"); return; }
  const nombre = (inputNombre.value || "").trim();
  if (!nombre) { showToast("Ingresa el nuevo nombre.", "info"); return; }

  const norm = s => s.normalize("NFKC").trim().toLowerCase();
  const yaExiste = carreras.some(c => c.id_Carrera !== idSeleccionado && norm(c.nombre) === norm(nombre));
  if (yaExiste) { showToast("❗ Ya existe otra carrera con ese nombre.", "info"); return; }

  try {
    const data = await fetchJson(API, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id_Carrera: idSeleccionado, nombre })
    });

    showToast(data.mensaje || "✏️ Carrera actualizada", "success");

    // actualizar en memoria sin recargar
    const idx = carreras.findIndex(x => x.id_Carrera === idSeleccionado);
    if (idx !== -1) carreras[idx].nombre = nombre;
    mostrarTabla();

    inputNombre.value = "";
    idSeleccionado = null;

  } catch (err) {
    showToast(err.message, "error", 3200);
  }
});

// ========== Eliminar ==========
document.querySelector(".eliminar").addEventListener("click", async () => {
  if (!idSeleccionado) { showToast("Selecciona una carrera primero.", "info"); return; }

  const ok = await showConfirm("¿Seguro que deseas eliminar esta carrera?");
  if (!ok) return;

  try {
    const params = new URLSearchParams({ id_Carrera: String(idSeleccionado) });
    const data = await fetchJson(`${API}?${params.toString()}`, { method: "DELETE" });

    showToast(data.mensaje || "🗑️ Carrera eliminada", "success");

    // quitar de memoria y re-render
    carreras = carreras.filter(c => c.id_Carrera !== idSeleccionado);
    mostrarTabla();

    inputNombre.value = "";
    idSeleccionado = null;

  } catch (err) {
    showToast(err.message || "No se pudo eliminar.", "error");
  }
});
