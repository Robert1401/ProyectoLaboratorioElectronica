/* =======================
   CONFIG
   ======================= */
// Si backend y frontend están en el mismo host/servidor PHP:
const API = "/backend/Registro-Compras.php";
// Si usas otro puerto distinto, pon la URL absoluta:
// const API = "http://127.0.0.1:8001/backend/Registro-Compras.php";

/* Auxiliar resuelto automáticamente desde el backend */
let AUX_NUMERO_CONTROL = null;

/* =======================
   ESTADO (lista en memoria)
   ======================= */
const state = {
  materiales: [],   // [{id_Material, nombre, cantidad (stock)}]
  items: [],        // [{id_Material, nombre, cantidad}]
  selIds: new Set() // ids seleccionados visualmente
};

/* =======================
   HELPERS
   ======================= */
async function fetchJson(url, options = {}) {
  const res  = await fetch(url, options);
  const text = await res.text();
  const ct   = res.headers.get("content-type") || "";
  console.log("FETCH", options.method || "GET", url, "->", res.status, ct, text);

  if (!ct.includes("application/json")) {
    throw new Error(`Respuesta no JSON (status ${res.status}). Cuerpo: ${text.slice(0, 300)}`);
  }
  let data;
  try { data = JSON.parse(text); }
  catch { throw new Error(`JSON inválido. Cuerpo: ${text.slice(0, 300)}`); }

  if (!res.ok) throw new Error(data.error || "Error de red");
  return data;
}

const $    = (sel) => document.querySelector(sel);
const $val = (sel) => ($(sel)?.value || "").trim();

function todayISO() {
  const d = new Date();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${d.getFullYear()}-${m}-${day}`;
}

function toast(msg, ms = 2200) {
  const t = $("#toast");
  if (!t) return alert(msg);
  t.textContent = msg;
  t.classList.add("show");
  setTimeout(() => t.classList.remove("show"), ms);
}

/* =======================
   AUXILIAR AUTO
   ======================= */
async function cargarAuxiliar() {
  try {
    const ncLocal = localStorage.getItem("numeroControl");
    const url = ncLocal
      ? `${API}?auxiliar=1&numeroControl=${encodeURIComponent(ncLocal)}`
      : `${API}?auxiliar=1`;

    const info = await fetchJson(url);
    AUX_NUMERO_CONTROL = info.numeroControl;

    const auxInput = $("#auxiliar");
    if (auxInput) {
      auxInput.value = info.nombre || "";
      auxInput.readOnly = true;
    }
  } catch (e) {
    console.error(e);
    const auxInput = $("#auxiliar");
    if (auxInput) {
      auxInput.value = "Auxiliar no disponible";
      auxInput.readOnly = true;
    }
  }
}

/* =======================
   TABLA (lista en memoria)
   ======================= */
function renderTablaLocal() {
  const tbody = $("#tablaMateriales tbody");
  tbody.innerHTML = "";

  if (state.items.length === 0) {
    const tr = document.createElement("tr");
    tr.innerHTML = `<td colspan="3" style="text-align:center; opacity:.7;">Sin artículos</td>`;
    tbody.appendChild(tr);
    return;
  }

  const fecha = $val("#fecha") || todayISO();

  state.items.forEach((it, idx) => {
    const tr = document.createElement("tr");
    tr.dataset.id = String(it.id_Material);
    if (state.selIds.has(it.id_Material)) tr.classList.add("selected");

    tr.innerHTML = `
      <td>${it.nombre}</td>
      <td>
        <input type="number" class="row-qty" min="1" value="${it.cantidad}" data-idx="${idx}" style="width:90px;">
      </td>
      <td>${fecha}</td>
    `;

    // Evita que el click dentro del input cambie selección
    tr.addEventListener("click", (ev) => {
      if ((ev.target instanceof HTMLElement) && ev.target.classList.contains("row-qty")) return;
      const id = it.id_Material;
      if (state.selIds.has(id)) state.selIds.delete(id);
      else state.selIds.add(id);
      renderTablaLocal();
    });

    tbody.appendChild(tr);
  });
}

function syncFromTableToState() {
  document.querySelectorAll("#tablaMateriales tbody .row-qty").forEach(inp => {
    const idx = Number(inp.getAttribute("data-idx"));
    const val = Math.max(1, parseInt(inp.value, 10) || 1);
    inp.value = String(val);
    if (state.items[idx]) state.items[idx].cantidad = val;
  });
}

function onFechaChange() { renderTablaLocal(); }

/* =======================
   CARGA DE MATERIALES
   ======================= */
async function cargarMateriales() {
  try {
    const mats = await fetchJson(`${API}?materiales=1`);
    state.materiales = mats;
    const sel = $("#material");
    if (sel) {
      sel.innerHTML = `<option value="">Seleccione un material</option>` +
        mats.map(m => `<option value="${m.id_Material}">${m.nombre} (stock: ${m.cantidad})</option>`).join("");
    }
  } catch (e) {
    alert("No se pudieron cargar los materiales: " + e.message);
  }
}

/* =======================
   AGREGAR ITEM
   ======================= */
function onPlusQty(e) {
  e.preventDefault();
  const inp = $("#cantidad");
  let v = parseInt(inp.value, 10);
  if (!Number.isFinite(v) || v < 0) v = 0;
  inp.value = String(v + 1);
}

function agregarItem() {
  const idMat = parseInt($val("#material"), 10);
  const fecha = $val("#fecha");

  if (!idMat) {
    alert("Selecciona un material.");
    $("#material")?.focus();
    return;
  }
  if (!fecha) {
    alert("Selecciona la fecha.");
    $("#fecha")?.focus();
    return;
  }

  let cantidad = parseInt($val("#cantidad"), 10);
  if (!Number.isFinite(cantidad) || cantidad <= 0) {
    alert("La cantidad debe ser un número mayor a 0.");
    $("#cantidad").value = "1";
    $("#cantidad").focus();
    return;
  }

  const mat = state.materiales.find(m => Number(m.id_Material) === idMat);
  if (!mat) return alert("Material no encontrado en el catálogo.");

  // Si ya existe en la lista local, SOLO incrementa cantidad
  const ya = state.items.find(it => it.id_Material === idMat);
  if (ya) ya.cantidad += cantidad;
  else state.items.push({ id_Material: idMat, nombre: mat.nombre, cantidad });

  state.selIds.clear();
  state.selIds.add(idMat);
  renderTablaLocal();

  // Limpia inputs y deja listo
  $("#material").value = "";
  $("#cantidad").value = "1";
  $("#material").focus();

  // Mensaje que pediste
  toast("✅ Material agregado. Presiona GUARDAR para subirlo a la BD.");
}

function onAgregar(e) {
  e.preventDefault();
  agregarItem();
}

/* =======================
   GUARDAR COMPRA
   ======================= */
async function onGuardar() {
  const fecha = $val("#fecha");
  if (!fecha) return alert("Selecciona la fecha.");

  syncFromTableToState();
  if (state.items.length === 0) return alert("Agrega al menos un material.");

  const payload = {
    fecha,
    numeroControl: AUX_NUMERO_CONTROL, // auxiliar auto
    items: state.items.map(it => ({
      id_Material: it.id_Material,
      cantidad: it.cantidad,
      gastoTotal: 0
    }))
  };

  try {
    const resp = await fetchJson(API, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });

    // Guardar ticket para la página de comprobante
    const ticket = {
      id_compra: resp.id_compra,
      fecha,
      auxiliar: resp.auxiliar || null,
      items: state.items.slice()
    };
    sessionStorage.setItem("ticket_compra", JSON.stringify(ticket));

    // Redirigir al ticket (ajusta ruta si tu árbol es distinto)
    window.location.href = "../../Tickets/ticket.html";
  } catch (e) {
    alert("❌ No se pudo guardar: " + e.message);
  }
}

/* =======================
   ELIMINAR (desde BD o local)
   ======================= */
let editTarget = null; // { id_compra, id_Material }

async function onEliminar() {
  if (editTarget && editTarget.id_compra && editTarget.id_Material) {
    if (!confirm("¿Eliminar esta línea de compra? Se ajustará el stock.")) return;
    try {
      const resp = await fetchJson(API, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "delete_item",
          id_compra: editTarget.id_compra,
          id_Material: editTarget.id_Material
        })
      });
      alert(`🗑️ ${resp.mensaje}`);
      editTarget = null;
      await onActualizar();
      await cargarMateriales();
    } catch (e) {
      alert("❌ No se pudo eliminar: " + e.message);
    }
    return;
  }

  // Lista local (quita el último)
  if (state.items.length === 0) return;
  state.items.pop();
  renderTablaLocal();
}

/* =======================
   HISTORIAL (desde BD)
   ======================= */
async function onActualizar() {
  try {
    const rows = await fetchJson(`${API}?compras=1&limit=50`);
    const tbody = $("#tablaMateriales tbody");
    tbody.innerHTML = "";

    if (!rows || rows.length === 0) {
      const tr = document.createElement("tr");
      tr.innerHTML = `<td colspan="3" style="text-align:center; opacity:.7;">Sin registros</td>`;
      tbody.appendChild(tr);
      state.items = [];
      return;
    }

    rows.forEach(r => {
      const f = (r.fechaIngreso || "").split(" ")[0] || r.fechaIngreso || "-";
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${r.nombreMaterial}</td>
        <td>${r.cantidadComprada} ${Number.isFinite(+r.stockActual) ? `(stock: ${r.stockActual})` : ""}</td>
        <td>${f}</td>
      `;
      tbody.appendChild(tr);
    });

    // lo visible ahora es BD, no la lista local
    state.items = [];
  } catch (e) {
    alert("No se pudo cargar el historial: " + e.message);
  }
}

/* =======================
   Delegación de eventos para inputs de tabla
   ======================= */
function attachTableDelegates() {
  const tbody = $("#tablaMateriales tbody");
  tbody.addEventListener("input", (ev) => {
    const t = ev.target;
    if (!(t instanceof HTMLInputElement)) return;
    if (!t.classList.contains("row-qty")) return;
    const idx = Number(t.getAttribute("data-idx"));
    const val = Math.max(1, parseInt(t.value, 10) || 1);
    t.value = String(val);
    if (state.items[idx]) state.items[idx].cantidad = val;
  });
}

/* =======================
   INIT
   ======================= */
async function init() {
  await cargarAuxiliar();
  await cargarMateriales();
  await onActualizar();

  $("#plusQty")?.addEventListener("click", onPlusQty);
  $("#agregar")?.addEventListener("click", onAgregar);
  $("#guardar")?.addEventListener("click", onGuardar);
  $("#eliminar")?.addEventListener("click", onEliminar);
  $("#actualizar")?.addEventListener("click", onActualizar);
  $("#fecha")?.addEventListener("change", onFechaChange);

  attachTableDelegates();

  if (!$val("#fecha")) $("#fecha").value = todayISO();
  renderTablaLocal();
}
document.addEventListener("DOMContentLoaded", init);
