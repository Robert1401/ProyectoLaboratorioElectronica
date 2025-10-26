// compras.js — Ticket (HOY / TODAS) sin “realizado” y con avisos centrados bonitos

const LS = {
  materiales: 'LE_materiales',
  compras: 'LE_compras',
  pendientes: 'LE_carrito_pend',
  auxiliar: 'auxiliarNombre',
};

const getMats = () => JSON.parse(localStorage.getItem(LS.materiales) || '[]');
const getCompras = () => JSON.parse(localStorage.getItem(LS.compras) || '[]');
const getPend = () => JSON.parse(localStorage.getItem(LS.pendientes) || '[]');
const getAux = () =>
  localStorage.getItem(LS.auxiliar) || 'Juan Vázquez Rodríguez';

function todayISO() {
  const d = new Date();
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${mm}-${dd}`;
}

/* ===============================
   Avisos bonitos al centro
   =============================== */
function ensureNotifyDOM() {
  if (document.getElementById('notify-layer')) return;
  const el = document.createElement('div');
  el.id = 'notify-layer';
  el.innerHTML = `
  <style>
    #notify-layer{position:fixed;inset:0;display:grid;place-items:center;pointer-events:none;z-index:10000;}
    .nv-wrap{min-width:280px;max-width:520px;opacity:0;transform:translateY(10px) scale(.98);
             transition:opacity .22s ease, transform .22s ease; pointer-events:auto;}
    .nv-card{border-radius:16px; padding:14px 16px; box-shadow:0 18px 48px rgba(0,0,0,.28);
             color:#fff; font-family:system-ui,Segoe UI,Roboto,Arial;}
    .nv-ok   {background:#065f46;}   /* verde */
    .nv-info {background:#1f2937;}   /* gris oscuro */
    .nv-err  {background:#991b1b;}   /* rojo */
    .nv-title{font-weight:700; margin:0 0 4px; font-size:14px; letter-spacing:.2px;}
    .nv-msg  {margin:0; opacity:.95; font-size:14px;}
    .nv-show {opacity:1; transform:translateY(0) scale(1);}
    .nv-close{margin-top:8px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);
              color:#fff;padding:6px 10px;border-radius:10px;font-size:12px;cursor:pointer;}
    .nv-close:hover{filter:brightness(1.1)}
  </style>
  <div class="nv-wrap">
    <div class="nv-card nv-info">
      <div class="nv-title">Aviso</div>
      <p class="nv-msg">...</p>
      <button class="nv-close" type="button">Cerrar</button>
    </div>
  </div>`;
  document.body.appendChild(el);
  el.querySelector('.nv-close').addEventListener('click', () => {
    el.querySelector('.nv-wrap').classList.remove('nv-show');
  });
}

function notify(msg, type = 'info', title) {
  ensureNotifyDOM();
  const wrap = document.querySelector('#notify-layer .nv-wrap');
  const card = document.querySelector('#notify-layer .nv-card');
  const ttl  = document.querySelector('#notify-layer .nv-title');
  const pmsg = document.querySelector('#notify-layer .nv-msg');

  card.className = `nv-card nv-${type === 'success' ? 'ok' : type === 'error' ? 'err' : 'info'}`;
  ttl.textContent = title || (type === 'success' ? 'Éxito' : type === 'error' ? 'Error' : 'Aviso');
  pmsg.textContent = msg;

  wrap.classList.add('nv-show');
  clearTimeout(notify._t);
  notify._t = setTimeout(() => wrap.classList.remove('nv-show'), 1700);
}

/* ===============================
   Unir COMPRAS guardadas + PENDIENTES por fecha
   =============================== */
function buildData() {
  const comprasRaw = getCompras()
    .slice()
    .map((c) => ({
      tipo: 'compra',                        // guardado
      id_compra: c.id_compra,
      fechaISO: (c.fecha || '').slice(0, 10),
      items: (c.items || []).map((it) => ({
        id_Material: it.id_Material,
        cantidad: it.cantidad,
      })),
    }));

  // Pendientes agrupadas por fecha
  const pend = getPend();
  const byDate = new Map();
  for (const p of pend) {
    const f = p.fechaISO;
    if (!byDate.has(f)) byDate.set(f, []);
    byDate.get(f).push({ id_Material: p.id_Material, cantidad: p.cantidad });
  }
  const pendBlocks = Array.from(byDate.entries()).map(([fechaISO, items]) => ({
    tipo: 'pend',                             // visualiza como parte del ticket (sin texto "pendiente")
    id_compra: '—',
    fechaISO,
    items,
  }));

  // Orden: fecha DESC; en misma fecha, compras primero
  return comprasRaw.concat(pendBlocks).sort((a, b) => {
    if (a.fechaISO !== b.fechaISO) return b.fechaISO.localeCompare(a.fechaISO);
    if (a.tipo !== b.tipo) return a.tipo === 'compra' ? -1 : 1;
    if (a.tipo === 'compra' && b.tipo === 'compra') return b.id_compra - a.id_compra;
    return 0;
  });
}

/* ===============================
   Render del ticket
   =============================== */
function renderRecibo({ modo = 'hoy' } = {}) {
  const mats = getMats();
  const aux  = getAux();

  const tbody      = document.getElementById('tbodyRecibo');
  const fechaMeta  = document.getElementById('fechaMeta');
  const auxInfo    = document.getElementById('auxInfo');
  const totalesInfo= document.getElementById('totalesInfo');

  auxInfo.textContent = `Auxiliar: ${aux}`;

  const blocks = buildData();
  let data = blocks;
  let meta = 'Todas las compras';

  if (modo === 'hoy') {
    const hoy = todayISO();
    data = blocks.filter((b) => b.fechaISO === hoy);
    meta = `Hoy ${hoy}`;
  }
  fechaMeta.textContent = meta;

  if (!data.length) {
    tbody.innerHTML = `<tr><td colspan="4" class="empty">${
      modo === 'hoy' ? 'No hay compras registradas hoy.' : 'No hay compras registradas.'
    }</td></tr>`;
    totalesInfo.textContent = '0 materiales';
    // aviso suave para UX
    notify(modo === 'hoy' ? 'Sin compras hoy.' : 'Sin registros.', 'info');
    return;
  }

  const rows = [];
  let totalMateriales = 0;

  for (const block of data) {
    const { id_compra, fechaISO, items = [] } = block;
    totalMateriales += items.length;

    items.forEach((it, idx) => {
      const m = mats.find((mm) => mm.id_Material === it.id_Material);
      const nombre = m ? m.nombre : `Material #${it.id_Material}`;

      rows.push(`
        <tr>
          ${
            idx === 0
              ? `<td rowspan="${items.length}" style="border-right:1px solid #e5e7eb;text-align:center;"><strong>${id_compra}</strong></td>`
              : ''
          }
          <td>${nombre}</td>
          <td>${it.cantidad}</td>
          <td>${fechaISO}</td>
        </tr>
      `);
    });
  }

  tbody.innerHTML = rows.join('');
  totalesInfo.textContent = `${totalMateriales} ${totalMateriales === 1 ? 'material' : 'materiales'}`;
}

/* ===============================
   Init + listeners
   =============================== */
document.addEventListener('DOMContentLoaded', () => {
  const btnToggle = document.getElementById('btnVerTodas');
  let modo = 'hoy';

  const setBtnText = () => {
    btnToggle.innerHTML =
      modo === 'hoy'
        ? `<i class="fa-solid fa-table-list"></i> Ver todas`
        : `<i class="fa-solid fa-calendar-day"></i> Ver hoy`;
  };

  renderRecibo({ modo });
  setBtnText();

  btnToggle?.addEventListener('click', () => {
    modo = modo === 'hoy' ? 'todas' : 'hoy';
    setBtnText();
    renderRecibo({ modo });
    notify(modo === 'hoy' ? 'Mostrando compras de hoy.' : 'Mostrando todas las compras.', 'info');
  });

  // Auto-refresco cuando cambian compras/pendientes/materiales desde otra pestaña o desde Registro
  window.addEventListener('storage', (e) => {
    if ([LS.compras, LS.pendientes, LS.materiales].includes(e.key)) {
      renderRecibo({ modo });
      notify('Recibo actualizado.', 'success');
    }
  });
});
