
/* =========================================================
   REGISTRO DE COMPRAS — 100% LOCAL (pendientes visibles)
   - Fecha limitada a HOY (no permite otro día)
   - Sin modal/tabla secundaria al pulsar el nombre
   - Tabla inicia vacía; suma si es el mismo material
   - Guardar => persiste, actualiza stock, limpia y redirige a ticket
========================================================= */

const LS = {
  materiales: 'LE_materiales',
  compras:    'LE_compras',
  idCompra:   'LE_idcompra_seq',
  pendientes: 'LE_carrito_pend',
  auxiliar:   'auxiliarNombre'
};
const DEFAULT_AUX = "Juan Vázquez Rodríguez";

/* -------- utils -------- */
const $ = s => document.querySelector(s);
const $val = s => ($(s)?.value || "").trim();
const todayISO = () => {
  const d = new Date();
  const mm = String(d.getMonth()+1).padStart(2,'0');
  const dd = String(d.getDate()).padStart(2,'0');
  return `${d.getFullYear()}-${mm}-${dd}`;
};
function clampInt(v,min=1){ const n=parseInt(String(v).replace(/[^\d]/g,''),10); return Number.isFinite(n)&&n>=min?n:min; }

/* ---------- Avisos (opcionales) ---------- */
function ensureNotifyDOM(){
  if ($('#notify-layer')) return;
  const div = document.createElement('div');
  div.id = 'notify-layer';
  div.innerHTML = `
    <style>
      #notify-layer{position:fixed;inset:0;display:grid;place-items:center;pointer-events:none;z-index:10000;}
      .nv-wrap{min-width:280px;max-width:520px;opacity:0;transform:translateY(10px) scale(.98);
               transition:opacity .22s ease, transform .22s ease; pointer-events:auto;}
      .nv-card{border-radius:14px; padding:14px 16px; box-shadow:0 18px 48px rgba(0,0,0,.25); color:#fff; font-family:system-ui,Segoe UI,Roboto,Arial;}
      .nv-ok {background:#065f46;}
      .nv-err{background:#991b1b;}
      .nv-info{background:#1f2937;}
      .nv-title{font-weight:700; margin:0 0 4px;}
      .nv-msg{margin:0; opacity:.95;}
      .nv-show{opacity:1; transform:translateY(0) scale(1);}
    </style>
    <div class="nv-wrap">
      <div class="nv-card nv-info">
        <div class="nv-title">Aviso</div>
        <div class="nv-msg">…</div>
      </div>
    </div>`;
  document.body.appendChild(div);
}
function notify(msg, type='info', title){
  ensureNotifyDOM();
  const wrap = $('#notify-layer .nv-wrap');
  const card = $('#notify-layer .nv-card');
  const ttl  = $('#notify-layer .nv-title');
  const pmsg = $('#notify-layer .nv-msg');
  card.className = `nv-card nv-${type==='success'?'ok':type==='error'?'err':'info'}`;
  ttl.textContent = title || (type==='success'?'Éxito':type==='error'?'Error':'Aviso');
  pmsg.textContent = msg;
  wrap.classList.add('nv-show');
  clearTimeout(notify._t);
  notify._t = setTimeout(()=>wrap.classList.remove('nv-show'), 1400);
}

/* -------- seed demo (opcional) -------- */
(function seed(){
  if (!localStorage.getItem(LS.materiales)){
    localStorage.setItem(LS.materiales, JSON.stringify([
      { id_Material:1, nombre:"Cable Dupont M-M (paquete 40)", cantidad:15 },
      { id_Material:2, nombre:"Capacitor Electrolítico 10µF", cantidad:300 },
      { id_Material:3, nombre:"Protoboard 830 puntos", cantidad:46 },
      { id_Material:4, nombre:"Resistor 220Ω", cantidad:400 }
    ]));
  }
  if (!localStorage.getItem(LS.compras)){
    localStorage.setItem(LS.compras, JSON.stringify([]));
    localStorage.setItem(LS.idCompra,"1");
  }
  if (!localStorage.getItem(LS.pendientes)) localStorage.setItem(LS.pendientes,"[]");
  if (!localStorage.getItem(LS.auxiliar)) localStorage.setItem(LS.auxiliar, DEFAULT_AUX);
})();

/* -------- DAO local -------- */
const DB = {
  getMats(){ return JSON.parse(localStorage.getItem(LS.materiales)||"[]"); },
  setMats(v){ localStorage.setItem(LS.materiales, JSON.stringify(v||[])); },

  getCompras(){ return JSON.parse(localStorage.getItem(LS.compras)||"[]"); },
  setCompras(v){ localStorage.setItem(LS.compras, JSON.stringify(v||[])); },

  getPend(){ return JSON.parse(localStorage.getItem(LS.pendientes)||"[]"); },
  setPend(v){ localStorage.setItem(LS.pendientes, JSON.stringify(v||[])); },

  nextCompraId(){ const n=parseInt(localStorage.getItem(LS.idCompra)||"1",10); localStorage.setItem(LS.idCompra,String(n+1)); return n; },

  getAux(){ return localStorage.getItem(LS.auxiliar)||DEFAULT_AUX; },
  setAux(n){ localStorage.setItem(LS.auxiliar, n||DEFAULT_AUX); }
};

/* -------- estado -------- */
const state = {
  materiales: [],
  pendientes: []   // [{id_Material, nombre, fechaISO, cantidad}]
};

/* -------- carga -------- */
function cargarMateriales(){
  state.materiales = DB.getMats();
  const sel = $("#material");
  if (sel){
    sel.innerHTML = `<option value="">Seleccione un material</option>` +
      state.materiales.map(m=>`<option value="${m.id_Material}">${m.nombre} (stock: ${m.cantidad})</option>`).join("");
  }
}
function cargarPendientes(){
  state.pendientes = DB.getPend(); // inicia vacío
}

/* -------- tabla (solo pendientes) -------- */
function renderTabla(){
  const tbody = $("#tablaMateriales tbody"); if (!tbody) return;
  tbody.innerHTML = "";

  const rows = state.pendientes.slice().sort((a,b)=> b.fechaISO.localeCompare(a.fechaISO) || a.nombre.localeCompare(b.nombre));
  if (!rows.length){
    tbody.innerHTML = `<tr><td colspan="3" style="text-align:center;opacity:.7;">Sin registros</td></tr>`;
    return;
  }
  for (const r of rows){
    const tr = document.createElement("tr");
    tr.dataset.idMaterial = r.id_Material;
    tr.innerHTML = `
      <td>${r.nombre}</td>
      <td>${r.cantidad}</td>
      <td>${r.fechaISO}</td>
    `;
    // ⬆️ Ya NO hay clickable ni modal al pulsar el nombre
    tbody.appendChild(tr);
  }
}

/* -------- acciones -------- */
function onPlusQty(e){ e?.preventDefault?.(); const inp=$("#cantidad"); inp.value=String(clampInt(inp.value,1)+1); }

/* Agregar: suma si es el mismo material (solo en pendientes) */
function onAgregar(e){
  e?.preventDefault?.();

  const idMat   = parseInt($val("#material"),10);
  const fechaEl = $("#fecha");
  const hoy     = todayISO();
  const fechaISO= (fechaEl && fechaEl.value) ? fechaEl.value : hoy;
  const cant    = clampInt($val("#cantidad"),1);

  if (!idMat){ notify("Selecciona un material.", "error"); $("#material")?.focus(); return; }
  const mat = state.materiales.find(m=>m.id_Material===idMat);
  if(!mat){ notify("Material no encontrado.", "error"); return; }

  // En esta vista solo pendientes: si ya existe, suma
  const i = state.pendientes.findIndex(p=>p.id_Material===idMat);
  if (i>=0){
    state.pendientes[i].cantidad += cant;
    // fecha siempre HOY; pero si hubiera valor distinto, forzamos a HOY
    state.pendientes[i].fechaISO = hoy;
  } else {
    state.pendientes.push({id_Material:idMat, nombre:mat.nombre, cantidad:cant, fechaISO:hoy});
  }
  DB.setPend(state.pendientes);

  // Forzar inputs a estado por defecto tras agregar
  if (fechaEl) fechaEl.value = hoy;
  $("#material").value=""; $("#cantidad").value="1"; $("#material").focus();

  renderTabla();
  notify("Agregado.", "success");
}

/* Guardar: consolida por HOY, actualiza stock, limpia y redirige al ticket */
function onGuardar(e){
  e?.preventDefault?.();
  if (!state.pendientes.length){ notify("No hay pendientes por guardar.", "info"); return; }

  const compras = DB.getCompras();
  const mats = DB.getMats();
  const hoy = todayISO();

  // Un solo bloque por HOY (todas las pendientes son de hoy)
  const id = DB.nextCompraId();
  const items = state.pendientes.map(p => ({ id_Material:p.id_Material, cantidad:p.cantidad, gastoTotal:0 }));
  compras.push({ id_compra:id, fecha:`${hoy} 00:00:00`, numeroControl:0, items });

  // Sumar a stock
  for (const it of items){
    const m = mats.find(mm=>mm.id_Material===it.id_Material);
    if (m) m.cantidad = (m.cantidad||0) + it.cantidad;
  }

  DB.setCompras(compras);
  DB.setMats(mats);

  // Limpiar pendientes + tabla
  state.pendientes = [];
  DB.setPend([]);
  renderTabla();

  // Ir al ticket (compras)
  location.href = "./compras.html";
}

/* -------- fecha: bloquear a HOY -------- */
function lockDateToToday(){
  const f = $("#fecha"); if (!f) return;
  const hoy = todayISO();
  f.value = hoy;
  f.min = hoy;
  f.max = hoy;
  f.setAttribute("aria-label","Fecha (hoy)");
  // Evita edición manual / días fuera
  f.addEventListener("change", ()=>{ if (f.value !== hoy) f.value = hoy; });
  f.addEventListener("input",  ()=>{ if (f.value !== hoy) f.value = hoy; });
  f.addEventListener("keydown",(e)=>{ e.preventDefault(); });  // sin tipeo
  f.addEventListener("paste", (e)=>{ e.preventDefault(); });   // sin pegar
}

/* -------- init -------- */
function init(){
  lockDateToToday();

  const aux=$("#auxiliar"); if (aux){ aux.value=DB.getAux(); aux.readOnly=true; }

  $("#plusQty")?.addEventListener("click", onPlusQty);
  $("#agregar")?.addEventListener("click", onAgregar);
  $("#guardar")?.addEventListener("click", onGuardar);
  $("#btnVerCompras")?.addEventListener("click", (e)=>{ e.preventDefault(); location.href="./compras.html"; });

  cargarMateriales();
  cargarPendientes();   // inicia vacío
  renderTabla();        // tabla vacía
}
document.addEventListener("DOMContentLoaded", init);

/* -------- estilos mínimos -------- */
(function css(){
  const s=document.createElement("style");
  s.textContent = `
    #tablaMateriales tbody tr td { vertical-align: middle; }
  `;
  document.head.appendChild(s);
})();

