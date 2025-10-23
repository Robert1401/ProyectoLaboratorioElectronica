/* ===== Helpers: Notice ===== */
function showNotice({title="Listo", message="", type="info", duration=1400}={}){
  const host = document.getElementById('noticeHost');
  if (!host) return;
  const iconMap = { success:'✔️', info:'ℹ️', warn:'⚠️', error:'⛔' };
  const icon = iconMap[type] || iconMap.info;
  host.innerHTML = `
    <div class="notice-card">
      <div class="notice-head">
        <div class="notice-icon ${type}">${icon}</div>
        <div>
          <h3 class="notice-title">${title}</h3>
          <p class="notice-msg">${message}</p>
        </div>
      </div>
      <div class="notice-timer"><i style="animation-duration:${duration}ms"></i></div>
    </div>
  `;
  host.classList.add('show');
  const t = setTimeout(()=>{ host.classList.remove('show'); host.innerHTML=''; }, duration+150);
  host.onclick = ()=>{ clearTimeout(t); host.classList.remove('show'); host.innerHTML=''; };
}

/* ===== Cargar payload guardado por solicitud-vale ===== */
const VALE_PAYLOAD_KEY = 'LE_vale_payload';
const ACTIVE_LOAN_KEY  = 'LE_prestamo_activo';
const ACTIVE_LOAN_DATA = 'LE_prestamo_data';

function renderPedido(payload){
  // Meta
  document.getElementById('metaFecha').textContent   = payload?.fecha || '—';
  document.getElementById('metaHora').textContent    = payload?.hora || '—';
  document.getElementById('metaVale').textContent    = payload?.noVale || '—';
  document.getElementById('metaMateria').textContent = payload?.materia || '—';
  document.getElementById('metaMaestro').textContent = payload?.maestro || '—';
  document.getElementById('metaMesa').textContent    = payload?.mesa || '—';

  const tbody = document.getElementById('tbodyPedido');
  const empty = document.getElementById('emptyState');

  tbody.innerHTML = '';
  const items = Array.isArray(payload?.items) ? payload.items : [];

  if (!items.length){
    empty.hidden = false;
    return;
  }
  empty.hidden = true;

  items.forEach(it=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${it.cantidad}</td><td>${it.descripcion}</td>`;
    tbody.appendChild(tr);
  });
}

document.addEventListener('DOMContentLoaded', ()=>{
  let payload = {};
  try { payload = JSON.parse(localStorage.getItem(VALE_PAYLOAD_KEY) || '{}'); } catch { payload = {}; }

  renderPedido(payload);

  // ACEPTAR PEDIDO → marca préstamo activo, guarda data y regresa al menú
  document.getElementById('btnAceptar')?.addEventListener('click', ()=>{
    // Guarda estado de préstamo activo
    localStorage.setItem(ACTIVE_LOAN_KEY, '1');
    localStorage.setItem(ACTIVE_LOAN_DATA, JSON.stringify(payload || {}));
    // (opcional) limpiar el puente del vale
    localStorage.removeItem(VALE_PAYLOAD_KEY);

    showNotice({
      title: 'Pedido aceptado',
      message: 'Tu préstamo quedó activo.',
      type: 'success',
      duration: 1200
    });
    setTimeout(()=> { window.location.href = 'alumnos-inicial.html'; }, 900);
  });
});
