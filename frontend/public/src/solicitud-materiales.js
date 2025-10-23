/* ========= Config ========= */
const LS_KEYS = {
  materiales: 'LE_materiales'   // [{ id_Material, nombre, cantidad }]
};
const TMP_SEL_KEY = 'LE_tmp_solicitud_vale'; // clave puente temporal

/* ===== Helpers ===== */
const $  = (s, ctx=document) => ctx.querySelector(s);
const $$ = (s, ctx=document) => [...ctx.querySelectorAll(s)];
const norm = (txt='') => txt.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();

/* ===== Catálogo ===== */
function getMats(){
  try { return JSON.parse(localStorage.getItem(LS_KEYS.materiales) || '[]'); }
  catch { return []; }
}

/* ===== Reglas dinámicas según stock ===== */
function limitFor(mat){
  const n = norm(mat?.nombre || '');
  const stock = Number(mat?.cantidad) || 0;

  // Reglas fijas por nombre exacto
  if (n.includes('capacitor electrolitico 10uf') || n.includes('capacitor electrolitico 10µf'))
    return Math.min(stock, 10);
  if (n.includes('resistor 220') || n.includes('resistencia 220'))
    return Math.min(stock, 10);
  if (n.includes('protoboard 830'))
    return Math.min(stock, 5);

  // Reglas generales según stock
  if (stock < 20) return stock;      // si hay pocos, solo los que hay
  if (stock < 100) return 5;         // si hay medianos, máx 5
  return 10;                         // si hay muchos, máx 10
}

/* ===== Dibujo de íconos ===== */
function iconFor(nombre = "") {
  const n = (nombre || "").toLowerCase();
  if (/(generador|funciones|signal|wave)/.test(n))
    return `<svg viewBox="0 0 64 64"><path d="M6 32 C12 12,20 12,26 32 S38 52,46 32 S56 12,58 12" fill="none" stroke-width="4"/></svg>`;
  if (/(fuente|voltaje|corriente)/.test(n))
    return `<svg viewBox="0 0 64 64"><rect x="8" y="14" width="48" height="36" rx="6" fill="none" stroke-width="3"/></svg>`;
  if (/(resistor|resistencia|ohm)/.test(n))
    return `<svg viewBox="0 0 64 64"><path d="M6 32 h12 l4 -8 l8 16 l8 -16 l8 16 l4 -8 h12" fill="none" stroke-width="3"/></svg>`;
  if (/(protoboard)/.test(n))
    return `<svg viewBox="0 0 64 64"><rect x="10" y="12" width="44" height="40" rx="6" fill="none" stroke-width="3"/></svg>`;
  return `<svg viewBox="0 0 64 64"><circle cx="32" cy="32" r="10" fill="none" stroke-width="3"/></svg>`;
}

/* ===== Render del catálogo ===== */
function renderCatalog(){
  const cont = $('#catalogo');
  const btn = $('#confirmar');
  cont.innerHTML = '';

  const mats = getMats();
  if (!mats.length){
    cont.innerHTML = `<p style="grid-column:1/-1;text-align:center">No hay materiales en el catálogo.</p>`;
    if (btn) btn.disabled = true;
    return;
  }

  for (const m of mats){
    const max = limitFor(m);
    const card = document.createElement('article');
    card.className = 'catalog-card';
    card.innerHTML = `
      <h3 class="prod-title">${m.nombre}</h3>
      <p class="specs"><b>Disponibles:</b> ${m.cantidad}</p>
      <div class="qty">
        <span>Seleccionar cantidad:</span>
        <input type="number" min="0" max="${max}" value="0">
        <div class="stepper">
          <button class="menos"><i class="fa-solid fa-minus"></i></button>
          <button class="mas"><i class="fa-solid fa-plus"></i></button>
        </div>
        <p class="subtle">Máx permitido: ${max}</p>
      </div>
    `;

    const input = card.querySelector('input');
    const btnMas = card.querySelector('.mas');
    const btnMen = card.querySelector('.menos');

    const clamp = () => {
      let v = parseInt(input.value || 0);
      if (Number.isNaN(v)) v = 0;
      input.value = Math.max(0, Math.min(max, v));
      updateConfirmState();
    };

    btnMas.addEventListener('click', () => { input.value = Math.min(max, parseInt(input.value || 0) + 1); clamp(); });
    btnMen.addEventListener('click', () => { input.value = Math.max(0, parseInt(input.value || 0) - 1); clamp(); });
    input.addEventListener('input', clamp);
    input.addEventListener('change', clamp);

    cont.appendChild(card);
  }

  updateConfirmState();
}

/* ===== Estado del botón Confirmar ===== */
function updateConfirmState(){
  const btn = $('#confirmar');
  if (!btn) return;
  const any = $$('#catalogo input[type="number"]').some(i => parseInt(i.value || 0) > 0);
  btn.disabled = !any;
}

/* ===== Confirmar selección ===== */
function onConfirm(){
  const cards = $$('#catalogo .catalog-card');
  const seleccion = cards.map(c => {
    const nombre = c.querySelector('.prod-title')?.textContent?.trim();
    const cantidad = parseInt(c.querySelector('input')?.value || 0);
    return cantidad ? { descripcion: nombre, cantidad } : null;
  }).filter(Boolean);

  if (!seleccion.length){
    alert('No has seleccionado cantidades.');
    return;
  }

  // Guardar la selección temporal para solicitud-vale
  localStorage.setItem(TMP_SEL_KEY, JSON.stringify(seleccion));

  // Redirigir a la página de vale
  window.location.href = 'solicitud-vale.html';
}

/* ===== Init ===== */
document.addEventListener('DOMContentLoaded', ()=>{
  renderCatalog();
  const btn = $('#confirmar');
  if (btn){
    btn.disabled = true;
    btn.addEventListener('click', onConfirm);
  }
  window.addEventListener('storage', e => {
    if (e.key === LS_KEYS.materiales) renderCatalog();
  });
});


/* ====== Notice centrado ====== */
function showNotice({title="Listo", message="", type="info", duration=2500}={}){
  const host = document.getElementById('noticeHost');
  if (!host) return;

  const iconMap = {
    success: '✔️', info:'ℹ️', warn:'⚠️', error:'⛔'
  };
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

  const t = setTimeout(()=> {
    host.classList.remove('show');
    host.innerHTML = '';
  }, duration + 180);

  // Cerrar al click
  host.onclick = () => {
    clearTimeout(t);
    host.classList.remove('show');
    host.innerHTML = '';
  };
}

/* ====== Confirm personalizado (Promise<boolean>) ====== */
function customConfirm({title="Confirmar", message="¿Deseas continuar?", confirmText="Sí", cancelText="Cancelar"}={}){
  return new Promise(resolve=>{
    const host = document.getElementById('modalHost');
    if (!host) return resolve(false);

    host.innerHTML = `
      <div class="modal-card">
        <div class="modal-head">
          <div class="notice-icon info">❓</div>
          <div>
            <h3 class="modal-title">${title}</h3>
            <p class="modal-msg">${message}</p>
          </div>
        </div>
        <div class="notice-actions">
          <button class="btn btn-ghost" id="mcCancel">${cancelText}</button>
          <button class="btn btn-primary" id="mcOk">${confirmText}</button>
        </div>
      </div>
    `;
    host.classList.add('show');

    const done = (val)=>{
      host.classList.remove('show');
      host.innerHTML = '';
      resolve(val);
    };

    host.querySelector('#mcCancel').onclick = ()=> done(false);
    host.querySelector('#mcOk').onclick     = ()=> done(true);

    // Cerrar con ESC o click fuera
    const onKey = (e)=> { if (e.key === 'Escape') { done(false); window.removeEventListener('keydown', onKey); } };
    window.addEventListener('keydown', onKey);
    host.addEventListener('click', (e)=> { if (e.target === host) done(false); }, { once:true });
  });
}

// CANCELAR → volver a solicitud-materiales.html con confirm personalizado
document.getElementById('btnCancelar')?.addEventListener('click', async ()=>{
  const ok = await customConfirm({
    title: "Cancelar vale",
    message: "¿Deseas cancelar y regresar al catálogo de materiales?",
    confirmText: "Sí, regresar",
    cancelText: "Seguir aquí"
  });
  if (ok){
    window.location.href = 'solicitud-materiales.html';
  }
});

// PEDIR → validar, mostrar notice y redirigir
document.getElementById('btnPedir')?.addEventListener('click', ()=>{
  const filas = [...document.querySelectorAll('#tbodyMateriales tr')].map(tr=>{
    const q = parseInt(tr.querySelector('.qty').value || 0, 10);
    const d = (tr.querySelector('.desc').value || '').trim();
    return q>0 && d ? { cantidad:q, descripcion:d } : null;
  }).filter(Boolean);

  if (!filas.length){
    showNotice({
      title: "Faltan datos",
      message: "Agrega al menos una fila con cantidad y descripción.",
      type: "warn",
      duration: 2600
    });
    return;
  }

  const payload = {
    fecha: document.getElementById('fecha').value,
    hora:  document.getElementById('hora').value,
    noVale: document.getElementById('noVale')?.value || '',
    materia: document.getElementById('materia').value,
    maestro: document.getElementById('maestro').value,
    mesa: document.getElementById('mesa').value,
    items: filas
  };

  console.log('PEDIDO', payload); // (si no quieres ver nada en consola, borra esta línea)

  showNotice({
    title: "¡Vale registrado!",
    message: "Tu solicitud se guardó correctamente.",
    type: "success",
    duration: 1800
  });

  // Redirección al “siguiente html”
  setTimeout(()=> {
    window.location.href = 'confirmacion.html'; // cambia por tu ruta real
  }, 1200);
});

showNotice({
  title: "Nada seleccionado",
  message: "Elige al menos 1 material para continuar.",
  type: "info",
  duration: 2000
});
