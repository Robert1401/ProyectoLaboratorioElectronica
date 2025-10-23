/* =========================================================
   PERSONAS — JS COMPLETO
   (Tabla fija con 3 registros + filtros + toasts + botones pro)
========================================================= */

/* ---------- Config / Storage ---------- */
const LS_KEYS = { USERS: 'LE_USERS_LOCAL', CAREERS: 'LE_CAREERS_LOCAL' };
const $  = (s)=>document.querySelector(s);
const $$ = (s)=>document.querySelectorAll(s);
const uid = ()=>Math.random().toString(36).slice(2,9);

/* Control: bloquear cambios para mantener exactamente 3 filas */
const LOCK_DATASET = true;   // <— pon false si quieres habilitar CRUD luego
const FORCE_SEED_ON_INIT = true; // fuerce el seed al cargar

/* ---------- Mensajes ---------- */
const Icons = {
  info:'<i class="fa-solid fa-circle-info"></i>',
  success:'<i class="fa-solid fa-circle-check"></i>',
  warning:'<i class="fa-solid fa-triangle-exclamation"></i>',
  error:'<i class="fa-solid fa-circle-exclamation"></i>'
};

function ensureMessagesRoot(){
  if(!$('#messages-root')){
    const d=document.createElement('div');
    d.id='messages-root';
    document.body.appendChild(d);
  }
}
function showMessage(type='info', text='', {timeout=2200, modal=false}={}){
  ensureMessagesRoot();
  const root=$('#messages-root');
  if(modal){
    const ov=document.createElement('div'); ov.className='msg-overlay show';
    const card=document.createElement('div'); card.className='msg-card show';
    card.innerHTML=`
      <div class="msg-icon">${Icons[type]||Icons.info}</div>
      <div class="msg-text">${text}</div>
      <div class="msg-actions"><button class="msg-btn">Aceptar</button></div>`;
    root.append(ov,card);
    const close=()=>{card.classList.remove('show');ov.classList.remove('show');setTimeout(()=>{card.remove();ov.remove();},160);};
    card.querySelector('.msg-btn').onclick=close; ov.onclick=close; return;
  }
  const toast=document.createElement('div'); toast.className=`msg-floating msg-type-${type}`;
  toast.innerHTML=`<div class="msg-icon">${Icons[type]||Icons.info}</div><div class="msg-text">${text}</div>`;
  root.appendChild(toast); requestAnimationFrame(()=>toast.classList.add('show'));
  const t=setTimeout(()=>close(),timeout); toast.addEventListener('click',()=>{clearTimeout(t);close();});
  function close(){ toast.classList.remove('show'); setTimeout(()=>toast.remove(),150); }
}
function showConfirm(text,{okText='Aceptar',cancelText='Cancelar'}={}){ 
  ensureMessagesRoot(); const root=$('#messages-root');
  return new Promise((resolve)=>{
    const ov=document.createElement('div'); ov.className='msg-overlay show';
    const card=document.createElement('div'); card.className='msg-card show';
    card.innerHTML=`<div class="msg-icon">${Icons.warning}</div>
                    <div class="msg-text">${text}</div>
                    <div class="msg-actions">
                      <button class="msg-btn cancel">${cancelText}</button>
                      <button class="msg-btn ok">${okText}</button></div>`;
    root.append(ov,card);
    const done=(ans)=>{card.classList.remove('show');ov.classList.remove('show');setTimeout(()=>{card.remove();ov.remove();resolve(ans);},160);};
    card.querySelector('.ok').onclick=()=>done(true);
    card.querySelector('.cancel').onclick=()=>done(false);
    ov.onclick=()=>done(false);
    document.addEventListener('keydown',function esc(e){ if(e.key==='Escape'){document.removeEventListener('keydown',esc);done(false);} });
  });
}

/* ---------- Roles (incluye ALL) ---------- */
function roleValueToName(v){
  if(v==='ALL')return'ALL';
  if(v==='1')return'Auxiliar';
  if(v==='2')return'Docente';
  if(v==='3')return'Alumno';
  return 'ALL';
}
function roleNameToValue(n){
  if(n==='ALL')return'ALL';
  if(n==='Auxiliar')return'1';
  if(n==='Docente')return'2';
  if(n==='Alumno')return'3';
  return 'ALL';
}

/* ---------- DAO ---------- */
function getUsers(){ return JSON.parse(localStorage.getItem(LS_KEYS.USERS)||'[]'); }
function setUsers(arr){ localStorage.setItem(LS_KEYS.USERS, JSON.stringify(arr||[])); }
function getCareers(){
  const raw = JSON.parse(localStorage.getItem(LS_KEYS.CAREERS)||'[]');
  if(raw.length) return raw;
  const seed=[
    {id_Carrera:1,nombre:'Ing. Sistemas'},
    {id_Carrera:2,nombre:'Ing. Mecánica'},
    {id_Carrera:3,nombre:'Ing. Electrónica'}
  ];
  localStorage.setItem(LS_KEYS.CAREERS, JSON.stringify(seed));
  return seed;
}

/* ---------- Semilla EXACTA (3 registros) ----------
   Basado en tus líneas:
   2040 2 1 Jesús Vázquez Rodriguez
   3001 3 1 Marta Ruiz Salas
   22050756 1 1 Mariana mota piña
----------------------------------------------------- */
const SEED_EXACTO = [
  { id: uid(), rol:'Docente',  numero:'2040',     nombre:'Jesús',   apellidoPaterno:'Vázquez', apellidoMaterno:'Rodriguez' },
  { id: uid(), rol:'Alumno',   numero:'3001',     nombre:'Marta',   apellidoPaterno:'Ruiz',    apellidoMaterno:'Salas' },
  { id: uid(), rol:'Auxiliar', numero:'22050756', nombre:'Mariana', apellidoPaterno:'mota',    apellidoMaterno:'piña' }
];

function aplicarSeedExacto(force=false){
  const curr = getUsers();
  if(force || !Array.isArray(curr) || curr.length!==3 ||
     JSON.stringify(curr.map(x=>x.numero).sort()) !== JSON.stringify(['2040','3001','22050756'].sort())){
    setUsers(SEED_EXACTO);
  }
}

/* ---------- UI helpers ---------- */
function mostrarElemento(){
  const sel = $('#ComboTipoRegistro').value;     // 'ALL' | '1' | '2' | '3'
  const rolNombre = roleValueToName(sel);
  // Aviso al usuario
  const personas = getUsers();
  const visibles = (rolNombre==='ALL') ? personas : personas.filter(p=>p.rol===rolNombre);
  showMessage('info', `Mostrando ${rolNombre === 'ALL' ? 'TODOS' : rolNombre} (${visibles.length} registro${visibles.length===1?'':'s'})`, {timeout:1800});

  // Si rol Alumno, podrías mostrar combo de carreras (aquí oculto por defecto)
  const esAlumno = (sel==='3');
  $('#spanCarreras')?.classList.toggle('Carreras', !esAlumno);

  renderTabla(); // refresca tabla con el filtro actual
}

function wireInputs(){
  $('#numeroControl')?.addEventListener('input', e=>{
    e.target.value = e.target.value.replace(/[^0-9]/g,'').slice(0,8);
  });
  ['nombre','paterno','materno'].forEach(id=>{
    $('#'+id)?.addEventListener('input', e=>{
      const limpio = e.target.value.replace(/[^A-Za-zÁÉÍÓÚáéíóúÑñ\s]/g,'');
      if(limpio!==e.target.value) e.target.value=limpio;
    });
  });
}
function cargarCarreras(){
  const combo=$('#comboCarreras'); if(!combo) return;
  const carreras=getCareers();
  combo.innerHTML = `<option value="">Seleccione</option>` +
    carreras.map(c=>`<option value="${c.id_Carrera}">${c.nombre}</option>`).join('');
}

/* ---------- Tabla (única) ---------- */
function renderTabla(){
  const tbody = $('#tablaFiltro tbody'); if(!tbody) return;
  const sel = $('#ComboTipoRegistro').value;
  const rol = roleValueToName(sel);
  const personas = getUsers();
  const datos = (rol==='ALL') ? personas : personas.filter(p=>p.rol===rol);

  if(!datos.length){
    tbody.innerHTML = `<tr><td class="empty" colspan="4">Sin registros para ${rol}</td></tr>`;
    return;
  }
  tbody.innerHTML = datos.map(p=>`
    <tr data-numero="${p.numero}" data-rol="${p.rol}">
      <td>${p.numero??''}</td>
      <td>${p.nombre??''}</td>
      <td>${p.apellidoPaterno??''}</td>
      <td>${p.apellidoMaterno??''}</td>
    </tr>`).join('');
}

/* ---------- CRUD (bloqueado por defecto) ---------- */
function onGuardar(){
  if(LOCK_DATASET){
    showMessage('warning','Edición bloqueada: la tabla debe mostrar únicamente los 3 registros solicitados.');
    return;
  }
  // (En caso de desbloquear, aquí estaría tu lógica de guardado)
}
function onModificar(){
  if(LOCK_DATASET){
    showMessage('warning','Edición bloqueada: la tabla debe mostrar únicamente los 3 registros solicitados.');
    return;
  }
  // (Lógica de actualización al desbloquear)
}
async function onEliminar(){
  if(LOCK_DATASET){
    showMessage('warning','Edición bloqueada: la tabla debe mostrar únicamente los 3 registros solicitados.');
    return;
  }
  // (Lógica de eliminación al desbloquear)
}

/* ---------- Selección en tabla ---------- */
function wireTablaSeleccion(){
  const tbody=$('#tablaFiltro tbody'); if(!tbody) return;
  tbody.addEventListener('click',e=>{
    const tr=e.target.closest('tr'); if(!tr) return;
    tbody.querySelectorAll('tr').forEach(r=>r.classList.remove('seleccion'));
    tr.classList.add('seleccion');

    const numero=tr.dataset.numero||'';
    const persona=getUsers().find(u=>u.numero===numero); if(!persona) return;

    const map={'Auxiliar':'1','Docente':'2','Alumno':'3'};
    $('#ComboTipoRegistro').value = map[persona.rol] || 'ALL';
    mostrarElemento();             // refresca tabla + mensaje
    $('#numeroControl').value = persona.numero || '';
    $('#nombre').value = persona.nombre || '';
    $('#paterno').value = persona.apellidoPaterno || '';
    $('#materno').value = persona.apellidoMaterno || '';
    $('#nombre')?.focus();
  });
}

/* ---------- Helpers form ---------- */
function limpiarCampos({alertar=false, limpiarBuscar=false}={}){
  ['numeroControl','nombre','paterno','materno'].forEach(id=>{
    const el=$('#'+id); if(el) el.value='';
  });
  const combo=$('#ComboTipoRegistro'); if(combo) combo.value='ALL';
  const carr=$('#comboCarreras'); if(carr) carr.value='';
  if(limpiarBuscar){ const b=$('#buscar'); if(b) b.value=''; }
  mostrarElemento(); if(alertar) showMessage('info','Formulario limpio.');
}

/* =========================================================
   EFECTOS PRO (botones): ripple + loading + pulso éxito
========================================================= */
function setBtnLoading(btn, isLoading, txtLoading = "Procesando…") {
  if (!btn) return;
  if (isLoading) {
    btn.dataset._txt = btn.textContent.trim();
    btn.classList.add("btn-loading");
    btn.setAttribute("disabled", "disabled");
    btn.textContent = txtLoading;
  } else {
    btn.classList.remove("btn-loading");
    btn.removeAttribute("disabled");
    if (btn.dataset._txt) btn.textContent = btn.dataset._txt;
  }
}
function attachRipple(btn) {
  if (!btn) return;
  btn.style.position = btn.style.position || "relative";
  btn.style.overflow = "hidden";
  btn.addEventListener("click", function (e) {
    const rect = btn.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const ripple = document.createElement("span");
    ripple.className = "btn-ripple";
    ripple.style.left = x + "px";
    ripple.style.top = y + "px";
    btn.appendChild(ripple);
    ripple.addEventListener("animationend", () => ripple.remove(), { once: true });
  });
}
function pulseSuccess(btn) {
  if (!btn) return;
  btn.classList.add("btn-okay");
  setTimeout(() => btn.classList.remove("btn-okay"), 650);
}
function enhanceButtons() {
  const ids = ["Guardar", "Modificar", "Eliminar", "CambiarClave"];
  ids.forEach(id => attachRipple(document.getElementById(id)));

  // Envoltorios: aunque CRUD esté bloqueado, damos feedback visual
  const bGuardar   = document.getElementById("Guardar");
  const bModificar = document.getElementById("Modificar");
  const bEliminar  = document.getElementById("Eliminar");

  if (bGuardar) {
    const original = onGuardar;
    window.onGuardar = function () {
      setBtnLoading(bGuardar, true, LOCK_DATASET ? "Bloqueado" : "Guardando…");
      try {
        original();
        pulseSuccess(bGuardar);
      } finally {
        setTimeout(() => setBtnLoading(bGuardar, false), 350);
      }
    };
  }
  if (bModificar) {
    const original = onModificar;
    window.onModificar = function () {
      setBtnLoading(bModificar, true, LOCK_DATASET ? "Bloqueado" : "Actualizando…");
      try {
        original();
        pulseSuccess(bModificar);
      } finally {
        setTimeout(() => setBtnLoading(bModificar, false), 350);
      }
    };
  }
  if (bEliminar) {
    const original = onEliminar;
    window.onEliminar = async function () {
      setBtnLoading(bEliminar, true, LOCK_DATASET ? "Bloqueado" : "Eliminando…");
      try {
        await original();
        pulseSuccess(bEliminar);
      } finally {
        setTimeout(() => setBtnLoading(bEliminar, false), 350);
      }
    };
  }
}

/* ---------- Init ---------- */
function init(){
  ensureMessagesRoot();

  // Semilla exacta (fija 3 filas)
  if (FORCE_SEED_ON_INIT) aplicarSeedExacto(true); else aplicarSeedExacto(false);

  wireInputs();
  cargarCarreras();

  // Arranca mostrando TODOS
  const combo = $('#ComboTipoRegistro');
  if (combo) combo.value='ALL';

  renderTabla();
  mostrarElemento();

  // Eventos
  $('#Guardar')?.addEventListener('click', onGuardar);
  $('#Modificar')?.addEventListener('click', onModificar);
  $('#Eliminar')?.addEventListener('click', onEliminar);
  $('#CambiarClave')?.addEventListener('click', ()=>showMessage('info','🔒 Cambiar contraseña (demo).',{timeout:2200}));
  $('#ComboTipoRegistro')?.addEventListener('change', mostrarElemento);

  // Buscar por número (Enter)
  $('#buscar')?.addEventListener('keydown', (e)=>{
    if(e.key!=='Enter') return;
    const q=e.target.value.trim(); if(!q) return;
    const fila=Array.from($$('#tablaFiltro tbody tr')).find(tr=>tr.dataset.numero===q);
    if(fila){ fila.scrollIntoView({behavior:'smooth',block:'center'}); fila.click(); showMessage('success','Usuario cargado desde la tabla.'); }
    else { showMessage('warning','No se encontró ese número en la tabla.'); }
  });

  wireTablaSeleccion();
  enhanceButtons();
}
document.addEventListener('DOMContentLoaded', init);

/* Exponer si usas algún botón externo para filtrar */
window.filtrarTablaPorRol = function(rolNombre){
  const valor = roleNameToValue(rolNombre);
  const combo = $('#ComboTipoRegistro');
  if (combo) combo.value = valor;
  mostrarElemento();
};
