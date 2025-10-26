/* ========= FECHA/HORA ========= */
function todayISO(){
  const d = new Date();
  const mm = String(d.getMonth()+1).padStart(2,'0');
  const dd = String(d.getDate()).padStart(2,'0');
  return `${d.getFullYear()}-${mm}-${dd}`;
}
function nowHM(){
  const d = new Date();
  return `${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
}

/* ========= SELECTS ========= */
function fillSelect(sel, items){
  sel.innerHTML = `<option value="">Seleccione…</option>` +
    items.map(x => `<option value="${x}">${x}</option>`).join('');
}

/* ========= PUENTE LOCALSTORAGE ========= */
const TMP_SEL_KEY = 'LE_tmp_solicitud_vale';

/* ========= CARGAR SELECCIÓN DESDE solicitud-materiales ========= */
function cargarSeleccionEnTabla(){
  const raw = localStorage.getItem(TMP_SEL_KEY);
  if (!raw) return;

  let seleccion = [];
  try { seleccion = JSON.parse(raw) || []; } catch { seleccion = []; }
  localStorage.removeItem(TMP_SEL_KEY);

  const tbody = document.getElementById('tbodyMateriales');
  if (!tbody) return;

  if (seleccion.length){
    tbody.innerHTML = '';
    seleccion.forEach(item=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><input type="number" min="0" step="1" class="qty" value="${item.cantidad}"></td>
        <td><input type="text" class="desc" value="${item.descripcion}"></td>
      `;
      tbody.appendChild(tr);
    });
  }
}

/* ========= NÚMERO DE VALE ========= */
function generarNumeroVale(){
  const d = new Date();
  const fecha = `${d.getFullYear()}${String(d.getMonth()+1).padStart(2,'0')}${String(d.getDate()).padStart(2,'0')}`;
  const hora = `${String(d.getHours()).padStart(2,'0')}${String(d.getMinutes()).padStart(2,'0')}${String(d.getSeconds()).padStart(2,'0')}`;
  return `VAL-${fecha}-${hora}`;
}

/* ========= UI HELPERS: NOTICE + CONFIRM ========= */
function showNotice({title="Listo", message="", type="info", duration=2500}={}){
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

  const t = setTimeout(()=> {
    host.classList.remove('show');
    host.innerHTML = '';
  }, duration + 180);

  host.onclick = () => {
    clearTimeout(t);
    host.classList.remove('show');
    host.innerHTML = '';
  };
}

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

    const onKey = (e)=> { if (e.key === 'Escape') { done(false); window.removeEventListener('keydown', onKey); } };
    window.addEventListener('keydown', onKey);
    host.addEventListener('click', (e)=> { if (e.target === host) done(false); }, { once:true });
  });
}

/* ========= VALIDAR FORMULARIO PARA HABILITAR “PEDIR” ========= */
function checkFormReady(){
  const f = document.getElementById('fecha');
  const h = document.getElementById('hora');
  const materia = document.getElementById('materia');
  const maestro = document.getElementById('maestro');
  const mesa = document.getElementById('mesa');
  const btn = document.getElementById('btnPedir');
  const filas = [...document.querySelectorAll('#tbodyMateriales tr')];

  const filledInputs = filas.some(tr=>{
    const q = parseInt(tr.querySelector('.qty').value || 0,10);
    const d = (tr.querySelector('.desc').value || '').trim();
    return q>0 && d;
  });

  const ready = (
    f.value &&
    h.value &&
    materia.value &&
    maestro.value &&
    mesa.value &&
    filledInputs
  );

  if (btn){
    btn.disabled = !ready;
    btn.classList.toggle('disabled', !ready);
  }
}

/* ========= INIT ========= */
document.addEventListener('DOMContentLoaded', ()=>{
  const f = document.getElementById('fecha');
  const h = document.getElementById('hora');
  if (f){ f.value = todayISO(); f.min = todayISO(); }
  if (h){ h.value = nowHM(); }

  // Número de vale
  const noVale = document.getElementById('noVale');
  if (noVale){
    noVale.value = generarNumeroVale();
    noVale.readOnly = true;
  }

  // Selects
  fillSelect(document.getElementById('materia'), [
    'Electrónica Analógica', 'Circuitos I', 'Mediciones', 'Instrumentación'
  ]);
  fillSelect(document.getElementById('maestro'), [
    'Ing. López', 'Mtra. García', 'Mtro. Hernández'
  ]);
  fillSelect(document.getElementById('mesa'), ['A1','A2','B1','B2','C1','C2']);

  // Cargar selección previa
  cargarSeleccionEnTabla();

  // Monitorear cambios para activar el botón
  document.querySelectorAll('input, select').forEach(el=>{
    el.addEventListener('input', checkFormReady);
    el.addEventListener('change', checkFormReady);
  });

  // Desactivar inicialmente el botón
  const btn = document.getElementById('btnPedir');
  if (btn){ btn.disabled = true; btn.classList.add('disabled'); }

  // CANCELAR
  document.getElementById('btnCancelar')?.addEventListener('click', async ()=>{
    const ok = await customConfirm({
      title: "Cancelar vale",
      message: "¿Deseas cancelar y regresar al catálogo de materiales?",
      confirmText: "Sí, regresar",
      cancelText: "Seguir aquí"
    });
    if (ok){ window.location.href = 'solicitud-materiales.html'; }
  });

  // PEDIR
  btn?.addEventListener('click', ()=>{
    const filas = [...document.querySelectorAll('#tbodyMateriales tr')].map(tr=>{
      const q = parseInt(tr.querySelector('.qty').value || 0, 10);
      const d = (tr.querySelector('.desc').value || '').trim();
      return q>0 && d ? { cantidad:q, descripcion:d } : null;
    }).filter(Boolean);

    if (!filas.length) return;

    const payload = {
      fecha: f.value,
      hora:  h.value,
      noVale: noVale ? noVale.value : '',
      materia: document.getElementById('materia').value,
      maestro: document.getElementById('maestro').value,
      mesa: document.getElementById('mesa').value,
      items: filas
    };

    showNotice({
      title: "¡Vale registrado!",
      message: "Tu solicitud se guardó correctamente.",
      type: "success",
      duration: 1800
    });

    // Guardar y redirigir
    localStorage.setItem('LE_vale_payload', JSON.stringify(payload));
    setTimeout(()=> window.location.href = 'confirmacion.html', 1200);
  });
});
