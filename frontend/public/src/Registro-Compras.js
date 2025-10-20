/* =======================
   CONFIG
   ======================= */
// Usa tu ruta real:
const API = "/backend/Registro-Compras.php";

/* Auxiliar resuelto automáticamente desde el backend */
let AUX_NUMERO_CONTROL = null;

/* =======================
   ESTADO
   ======================= */
const state = {
  materiales: [],      // catálogo [{id_Material, nombre, cantidad}]
  carrito: [],         // items por guardar [{id_Material, nombre, cantidad}]
  history: [],         // historial traído de BD
  historySelected: null // { id_compra, id_Material }
};

/* =======================
   HELPERS
   ======================= */
async function fetchJson(url, options = {}) {
  const res  = await fetch(url, options);
  const txt  = await res.text();
  let data;
  try { data = JSON.parse(txt); }
  catch { throw new Error(`Respuesta no JSON (${res.status}). ${txt.slice(0,200)}`); }
  if (!res.ok) throw new Error(data.error || "Error de red");
  return data;
}
const $    = (s) => document.querySelector(s);
const $val = (s) => ($(s)?.value || "").trim();

function todayISO() {
  const d = new Date();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${d.getFullYear()}-${m}-${day}`;
}

function toast(msg, ms=1800) {
  const t = $("#toast");
  if (!t) return alert(msg);
  t.textContent = msg;
  t.classList.add("show");
  setTimeout(() => t.classList.remove("show"), ms);
}

function pulse(el, cls="pulse", ms=400) {
  if (!el) return;
  el.classList.add(cls);
  setTimeout(()=>el.classList.remove(cls), ms);
}

/* =======================
   AUXILIAR AUTO
   ======================= */
async function cargarAuxiliar() {
  try {
    const nc = localStorage.getItem("numeroControl");
    const info = await fetchJson(nc ? `${API}?auxiliar=1&numeroControl=${encodeURIComponent(nc)}` : `${API}?auxiliar=1`);
    AUX_NUMERO_CONTROL = info.numeroControl;
    $("#auxiliar").value = info.nombre || "";
  } catch {
    $("#auxiliar").value = "Auxiliar no disponible";
  }
  $("#auxiliar").readOnly = true;
}

/* =======================
   CATALOGO
   ======================= */
async function cargarMateriales() {
  try {
    const mats = await fetchJson(`${API}?materiales=1`);
    state.materiales = mats;
    const sel = $("#material");
    sel.innerHTML = `<option value="">Seleccione un material</option>` +
      mats.map(m => `<option value="${m.id_Material}">${m.nombre} (stock: ${m.cantidad})</option>`).join("");
  } catch (e) {
    alert("No se pudieron cargar los materiales: " + e.message);
  }
}

/* =======================
   HISTORIAL (BD SIEMPRE EN TABLA)
   ======================= */
async function cargarHistorial() {
  const tbody = $("#tablaMateriales tbody");
  tbody.innerHTML = `<tr><td colspan="3" style="text-align:center;opacity:.7;">Cargando...</td></tr>`;
  try {
    const rows = await fetchJson(`${API}?compras=1&limit=50`);
    state.history = rows || [];
    renderHistorial();
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="3" style="text-align:center;opacity:.7;">Error: ${e.message}</td></tr>`;
  }
}

function renderHistorial() {
  const tbody = $("#tablaMateriales tbody");
  tbody.innerHTML = "";
  if (!state.history.length) {
    tbody.innerHTML = `<tr><td colspan="3" style="text-align:center;opacity:.7;">Sin registros</td></tr>`;
    state.historySelected = null;
    return;
  }

  state.history.forEach(r => {
    const f = (r.fechaIngreso || "").toString().split(" ")[0] || r.fechaIngreso || "-";
    const tr = document.createElement("tr");
    tr.dataset.idCompra = r.id_compra;
    tr.dataset.idMaterial = r.id_Material;
    tr.innerHTML = `
      <td>${r.nombreMaterial}</td>
      <td>${r.cantidadComprada} ${Number.isFinite(+r.stockActual) ? `(stock: ${r.stockActual})` : ""}</td>
      <td>${f}</td>
    `;
    tr.addEventListener("click", () => {
      // marcar selección
      tbody.querySelectorAll("tr").forEach(x=>x.classList.remove("selected"));
      tr.classList.add("selected");
      state.historySelected = { id_compra: Number(r.id_compra), id_Material: Number(r.id_Material) };
    });
    tbody.appendChild(tr);
  });
}

/* =======================
   CARRITO (local)
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
  let cantidad = parseInt($val("#cantidad"), 10);

  if (!idMat) { alert("Selecciona un material."); $("#material")?.focus(); return; }
  if (!fecha)  { alert("Selecciona la fecha."); $("#fecha")?.focus();    return; }
  if (!Number.isFinite(cantidad) || cantidad <= 0) {
    alert("La cantidad debe ser mayor a 0."); $("#cantidad").value="1"; $("#cantidad").focus(); return;
  }

  const mat = state.materiales.find(m => Number(m.id_Material) === idMat);
  if (!mat) return alert("Material no encontrado.");

  const ya = state.carrito.find(it => it.id_Material === idMat);
  if (ya) ya.cantidad += cantidad;
  else state.carrito.push({ id_Material: idMat, nombre: mat.nombre, cantidad });

  // feedback visual en el botón Guardar
  toast("✅ Material agregado al carrito. Presiona GUARDAR para subir a la BD.");
  pulse($("#guardar"), "pulse-strong", 600);

  // reset inputs
  $("#material").value = "";
  $("#cantidad").value = "1";
  $("#material").focus();
}

function onAgregar(e){ e.preventDefault(); agregarItem(); }

/* =======================
   GUARDAR -> BD (+ autorefresco + ticket)
   ======================= */
async function onGuardar() {
  const fecha = $val("#fecha");
  if (!fecha) return alert("Selecciona la fecha.");
  if (!state.carrito.length) return alert("Agrega al menos un material.");

  const payload = {
    fecha,
    numeroControl: AUX_NUMERO_CONTROL,
    items: state.carrito.map(it => ({ id_Material: it.id_Material, cantidad: it.cantidad, gastoTotal: 0 }))
  };

  try {
    const resp = await fetchJson(API, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });

    // ticket
    const ticket = {
      id_compra: resp.id_compra,
      fecha,
      auxiliar: resp.auxiliar || null,
      items: state.carrito.slice()
    };
    sessionStorage.setItem("ticket_compra", JSON.stringify(ticket));

    // vaciar carrito y REFRESCAR TABLA BD (sin tocar Actualizar)
    state.carrito = [];
    await cargarHistorial();
    toast("✅ Compra guardada. Tabla actualizada.");

    // ir al ticket (si no quieres salir de la vista, comenta la siguiente línea)
    window.location.href = "../../Tickets/ticket.html";
  } catch (e) {
    alert("❌ No se pudo guardar: " + e.message);
  }
}

/* =======================
   ELIMINAR (modal con parcial / total)
   ======================= */
function openEliminarModal(prefSel=null) {
  // construir modal si no existe
  let dlg = document.getElementById("dlgEliminar");
  if (!dlg) {
    dlg = document.createElement("div");
    dlg.id = "dlgEliminar";
    dlg.innerHTML = `
      <div class="dlg-mask" style="position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;z-index:1000;">
        <div class="dlg-card" style="background:#fff;min-width:360px;max-width:520px;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,.25);padding:16px;">
          <h3 style="margin:0 0 8px;">Eliminar del historial</h3>
          <p style="margin:0 0 10px;opacity:.8;">Selecciona la línea y la cantidad a eliminar.</p>
          <div style="display:grid;gap:8px;">
            <label>Producto / Compra:
              <select id="dl_line" style="width:100%;padding:8px;border-radius:8px;border:1px solid #ddd;"></select>
            </label>
            <label>Cantidad a eliminar:
              <input id="dl_qty" type="number" min="1" value="1" style="width:100%;padding:8px;border-radius:8px;border:1px solid #ddd;">
            </label>
          </div>
          <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px;">
            <button id="dl_cancel" class="btn">Cancelar</button>
            <button id="dl_partial" class="btn btn-secondary">Eliminar cantidad</button>
            <button id="dl_total" class="btn btn-danger">Eliminar todo</button>
          </div>
        </div>
      </div>`;
    document.body.appendChild(dlg);
  }

  // llenar opciones
  const sel = dlg.querySelector("#dl_line");
  sel.innerHTML = "";
  state.history.forEach(r => {
    const txt = `${r.nombreMaterial} — cant: ${r.cantidadComprada} — compra #${r.id_compra}`;
    const opt = document.createElement("option");
    opt.value = JSON.stringify({ id_compra: r.id_compra, id_Material: r.id_Material, max: r.cantidadComprada });
    opt.textContent = txt;
    sel.appendChild(opt);
  });

  // preselección si venimos de un click en la tabla
  if (prefSel) {
    const val = JSON.stringify(prefSel);
    const opt = [...sel.options].find(o => {
      const v = JSON.parse(o.value);
      return v.id_compra == prefSel.id_compra && v.id_Material == prefSel.id_Material;
    });
    if (opt) sel.value = opt.value;
  }

  // listeners
  const cancel = dlg.querySelector("#dl_cancel");
  const partial = dlg.querySelector("#dl_partial");
  const total = dlg.querySelector("#dl_total");
  const qtyInp = dlg.querySelector("#dl_qty");

  function close() { dlg.remove(); }
  cancel.onclick = close;

  partial.onclick = async () => {
    const chosen = JSON.parse(sel.value);
    let q = parseInt(qtyInp.value, 10);
    if (!Number.isFinite(q) || q <= 0) return alert("Cantidad inválida.");
    if (q > Number(chosen.max)) return alert("No puedes eliminar más que la cantidad comprada.");
    try {
      await fetchJson(API, {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify({
          action: "decrement_item",
          id_compra: Number(chosen.id_compra),
          id_Material: Number(chosen.id_Material),
          cantidad: q
        })
      });
      close();
      await cargarHistorial();
      await cargarMateriales();
      toast("✅ Cantidad eliminada. Tabla actualizada.");
    } catch(e) {
      alert("❌ No se pudo eliminar parcialmente: "+e.message);
    }
  };

  total.onclick = async () => {
    const chosen = JSON.parse(sel.value);
    if (!confirm("¿Eliminar TODA la línea seleccionada?")) return;
    try {
      await fetchJson(API, {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify({
          action: "delete_item",
          id_compra: Number(chosen.id_compra),
          id_Material: Number(chosen.id_Material)
        })
      });
      close();
      await cargarHistorial();
      await cargarMateriales();
      toast("🗑️ Línea eliminada. Tabla actualizada.");
    } catch(e) {
      alert("❌ No se pudo eliminar la línea: "+e.message);
    }
  };
}

function onEliminar() {
  // si el usuario ya seleccionó una fila, se preselecciona en el modal
  const pref = state.historySelected ? { ...state.historySelected } : null;
  openEliminarModal(pref);
}

/* =======================
   INIT
   ======================= */
async function init() {
  $("#fecha").value = todayISO();
  await cargarAuxiliar();
  await cargarMateriales();
  await cargarHistorial();

  $("#plusQty").addEventListener("click", onPlusQty);
  $("#agregar").addEventListener("click", onAgregar);
  $("#guardar").addEventListener("click", onGuardar);
  $("#eliminar").addEventListener("click", onEliminar);
  $("#actualizar").addEventListener("click", cargarHistorial);
  $("#fecha").addEventListener("change", ()=>{/*la fecha solo aplica al guardar*/});
}
document.addEventListener("DOMContentLoaded", init);

/* ====== estilos rápidos para feedback ====== */
const style = document.createElement("style");
style.textContent = `
  .selected { background: rgba(127,0,0,.08); }
  .pulse-strong { transform: scale(1.05); transition: transform .2s; }
  .pulse-strong:where(:not(:active)) { transform: none; transition: transform .2s; }
`;
document.head.appendChild(style);
