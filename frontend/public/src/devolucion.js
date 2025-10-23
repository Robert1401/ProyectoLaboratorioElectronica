/* ========== NOTICE ========== */
function showNotice({title="Listo", message="", type="info", duration=2000}={}){
  const host = document.getElementById('noticeHost');
  if (!host) return;
  const iconMap = { success:'✔️', info:'ℹ️', warn:'⚠️', error:'⛔' };
  const icon = iconMap[type] || iconMap.info;
  host.innerHTML = `
    <div class="notice-card">
      <div class="notice-head">
        <div class="notice-icon ${type}">${icon}</div>
        <div><h3 class="notice-title">${title}</h3><p class="notice-msg">${message}</p></div>
      </div>
    </div>`;
  host.classList.add('show');
  const t = setTimeout(()=>{host.classList.remove('show');host.innerHTML='';}, duration);
  host.onclick=()=>{clearTimeout(t);host.classList.remove('show');host.innerHTML='';};
}

const ACTIVE_LOAN_KEY  = 'LE_prestamo_activo';
const ACTIVE_LOAN_DATA = 'LE_prestamo_data';
const DEV_HISTORY_KEY  = 'LE_historial_devoluciones';

function getLoan(){
  try { return JSON.parse(localStorage.getItem(ACTIVE_LOAN_DATA) || '{}'); } catch { return {}; }
}
function setLoanActive(active){
  localStorage.setItem(ACTIVE_LOAN_KEY, active ? '1' : '0');
}
function pushHistory(entry){
  let arr = [];
  try { arr = JSON.parse(localStorage.getItem(DEV_HISTORY_KEY) || '[]'); } catch { arr = []; }
  arr.push(entry);
  localStorage.setItem(DEV_HISTORY_KEY, JSON.stringify(arr));
}

function renderPrestamo(){
  const lista = document.getElementById('listaPrestamos');
  if (!lista) return;
  const loan = getLoan();
  const items = Array.isArray(loan?.items) ? loan.items : [];

  lista.innerHTML = '';
  if (!items.length){
    lista.innerHTML = `<div class="item" style="justify-content:center;color:#777">No hay materiales pendientes.</div>`;
    return;
  }

  items.forEach(it=>{
    const row = document.createElement('div');
    row.className = 'item';
    row.innerHTML = `
      <i class="fa-solid fa-clipboard-list icono"></i>
      <input type="text" value="${it.descripcion}  —  x${it.cantidad}" readonly>
    `;
    lista.appendChild(row);
  });
}

document.addEventListener('DOMContentLoaded', ()=>{
  // fecha por defecto
  const f = document.getElementById('fecha');
  if (f){
    const d = new Date(); const mm = String(d.getMonth()+1).padStart(2,'0'); const dd = String(d.getDate()).padStart(2,'0');
    f.value = `${d.getFullYear()}-${mm}-${dd}`;
  }

  // Pinta los materiales prestados
  renderPrestamo();

  // Confirmar devolución
  document.getElementById('btnConfirmar')?.addEventListener('click', ()=>{
    const materia = document.getElementById('materia').value.trim();
    const maestro = document.getElementById('maestro').value.trim();
    const mesa    = document.getElementById('mesa').value.trim();
    if(!materia || !maestro || !mesa){
      showNotice({title:'Campos incompletos', message:'Completa todos los campos.', type:'warn'});
      return;
    }

    const payload = getLoan();
    // guarda historial
    pushHistory({
      devueltoEn: new Date().toISOString(),
      materia, maestro, mesa,
      prestamo: payload
    });

    // limpia préstamo activo
    setLoanActive(false);
    localStorage.removeItem(ACTIVE_LOAN_DATA);

    showNotice({title:'Devolución registrada', message:'Gracias por devolver el material.', type:'success'});
    setTimeout(()=> window.location.href = 'alumnos-inicial.html', 1200);
  });

  // Cancelar → menú
  document.getElementById('btnCancelar')?.addEventListener('click', ()=>{
    showNotice({title:'Cancelado', message:'Regresando al menú…', type:'info', duration:1200});
    setTimeout(()=> window.location.href = 'alumnos-inicial.html', 900);
  });
});
