/* ================================
   CONFIG / HELPERS
================================ */
const API_URL = "../../../../backend/usuarios.php"; // ajusta si tu ruta cambia
const DEBUG = false;

const $  = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

function roleValueToName(v){ if(v==="1")return"Auxiliar"; if(v==="2")return"Docente"; if(v==="3")return"Alumno"; return""; }
function roleNameToValue(n){ if(n==="Auxiliar")return"1"; if(n==="Docente")return"2"; if(n==="Alumno")return"3"; return""; }

async function fetchJson(url, options = {}) {
  const res  = await fetch(url, options);
  const text = await res.text();
  if (DEBUG) console.log("FETCH", options.method||"GET", url, "status", res.status, "resp:", text);
  let data;
  try { data = JSON.parse(text); } catch { throw new Error("Respuesta no JSON"); }
  if (!res.ok) throw new Error(data.error || "Error de red");
  return data;
}

/* ================================
   SISTEMA DE MENSAJES CENTRADOS
================================ */
const Icons = {
  info:    '<i class="fa-solid fa-circle-info"></i>',
  success: '<i class="fa-solid fa-circle-check"></i>',
  warning: '<i class="fa-solid fa-triangle-exclamation"></i>',
  error:   '<i class="fa-solid fa-circle-exclamation"></i>'
};

function showMessage(type = 'info', text = '', { timeout, modal = false } = {}) {
  const root = $('#messages-root');
  if (!root) return alert(text);

  const overlay = document.createElement('div');
  overlay.className = 'msg-overlay' + (modal ? ' show' : '');
  overlay.style.display = modal ? 'block' : 'none';

  const card = document.createElement('div');
  card.className = `msg-card msg-type-${type} msg-floating`;
  card.innerHTML = `
    <div class="msg-icon">${Icons[type] || Icons.info}</div>
    <div class="msg-text">${text}</div>
  `;

  root.appendChild(overlay);
  root.appendChild(card);
  requestAnimationFrame(()=>card.classList.add('show'));

  if (!modal) {
    overlay.style.display = 'none';
    const to = timeout ?? 2200;
    const t = setTimeout(() => close(), to);
    card.addEventListener('click', () => { clearTimeout(t); close(); });
  }

  function close() {
    card.classList.remove('show');
    overlay.classList.remove('show');
    setTimeout(() => { card.remove(); overlay.remove(); }, 160);
  }
  return { close };
}

function showConfirm(text, { okText = "Aceptar", cancelText = "Cancelar" } = {}) {
  return new Promise((resolve) => {
    const root = $('#messages-root');
    if (!root) return resolve(confirm(text));

    const overlay = document.createElement('div');
    overlay.className = 'msg-overlay show';

    const card = document.createElement('div');
    card.className = 'msg-card msg-type-warning';
    card.innerHTML = `
      <div class="msg-icon">${Icons.warning}</div>
      <div class="msg-text">${text}</div>
      <div class="msg-actions">
        <button class="msg-btn cancel">${cancelText}</button>
        <button class="msg-btn ok">${okText}</button>
      </div>
    `;

    root.appendChild(overlay);
    root.appendChild(card);
    requestAnimationFrame(()=>card.classList.add('show'));

    const btnOk = card.querySelector('.msg-btn.ok');
    const btnCancel = card.querySelector('.msg-btn.cancel');

    function cleanup(answer) {
      card.classList.remove('show');
      overlay.classList.remove('show');
      setTimeout(() => { card.remove(); overlay.remove(); resolve(answer); }, 160);
    }

    btnOk.addEventListener('click',   () => cleanup(true));
    btnCancel.addEventListener('click', () => cleanup(false));
    overlay.addEventListener('click', () => cleanup(false));
    document.addEventListener('keydown', function esc(e){
      if (e.key === 'Escape') { document.removeEventListener('keydown', esc); cleanup(false); }
    });
  });
}

/* ================================
   MOSTRAR/OCULTAR CAMPOS (Alumno)
================================ */
function mostrarElemento() {
  const select = $('#ComboTipoRegistro');
  const comboCarreras = $('#comboCarreras');
  const spanCarreras  = $('#spanCarreras');

  const esAlumno = select.value === '3';
  comboCarreras.style.display = esAlumno ? 'block' : 'none';
  spanCarreras.style.display  = esAlumno ? 'block' : 'none';
}

/* ================================
   SANITIZADO Y VALIDACIÓN
================================ */
function wireInputs() {
  const numeroControl = $('#numeroControl');
  const soloLetras = [/[^A-Za-zÁÉÍÓÚáéíóúÑñ\s]/g, ''];
  const camposLetras = ['nombre','paterno','materno'].map(id => $('#'+id));

  numeroControl.addEventListener('input', (e)=>{
    e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0,8);
  });
  camposLetras.forEach(el=>{
    el.addEventListener('input', (e)=>{
      const limpio = e.target.value.replace(soloLetras[0], soloLetras[1]);
      if (limpio !== e.target.value) e.target.value = limpio;
    });
  });
}

function validarFormulario() {
  const form = $('#Formulario');
  if (!form.checkValidity()) {
    form.reportValidity();
    return false;
  }
  return true;
}

/* ================================
   CARGA DE CATÁLOGOS
================================ */
async function cargarCarreras() {
  const combo = $('#comboCarreras');
  try {
    const carreras = await fetchJson(`${API_URL}?carreras=1`);
    combo.innerHTML = `<option value="">Seleccione</option>` +
      carreras.map(c => `<option value="${c.id_Carrera}">${c.nombre}</option>`).join("");
  } catch {
    combo.innerHTML = `<option value="">(sin datos)</option>`;
    showMessage('warning', 'No se pudieron cargar las carreras.');
  }
}

/* ================================
   UI HELPERS
================================ */
function limpiarCampos({ alertar=false, limpiarBuscar=false }={}) {
  ['numeroControl','nombre','paterno','materno'].forEach(id => { const el = $('#'+id); if (el) el.value = ""; });
  $('#ComboTipoRegistro').value = "1";
  const combo = $('#comboCarreras'); if (combo) combo.value = "";
  mostrarElemento();
  if (limpiarBuscar) { const b = $('#buscar'); if (b) b.value = ""; }
  if (alertar) showMessage('info', 'Formulario limpio.');
}

function pintarSeleccionTabla(tr) {
  const tbody = $('#tablaDinamica tbody');
  if (!tbody) return;
  tbody.querySelectorAll('tr').forEach(r=>r.classList.remove('seleccion'));
  if (tr) tr.classList.add('seleccion');
}

/* ================================
   BUSCAR (si mantienes buscador)
================================ */
async function buscarUsuario() {
  const qEl = $("#buscar");
  if (!qEl) return;
  const q = qEl.value.trim();
  if (!q) return showMessage('warning', "Ingresa un número de control / trabajador o usuario");

  try {
    const data = await fetchJson(`${API_URL}?num=${encodeURIComponent(q)}&mostrar_hash=1`);
    limpiarCampos();
    $('#ComboTipoRegistro').value = roleNameToValue(data.rol || "Auxiliar");
    $('#nombre').value  = data.nombre || "";
    $('#paterno').value = data.apellidoPaterno || "";
    $('#materno').value = data.apellidoMaterno || "";

    if (data.rol === "Alumno") {
      $('#numeroControl').value = data.numeroControl || "";
      if (data.id_Carrera) $('#comboCarreras').value = String(data.id_Carrera);
    } else if (data.rol === "Auxiliar") {
      $('#numeroControl').value = data.numeroTrabajador || "";
      $('#comboCarreras').value = "";
    } else if (data.rol === "Docente") {
      $('#numeroControl').value = data.numeroTrabajador || data.numeroControl || "";
      $('#comboCarreras').value = "";
    }

    mostrarElemento();
    window.__ultimo = data;
    showMessage('success', 'Usuario encontrado.');
  } catch (err) {
    window.__ultimo = null;
    showMessage('error', 'No se encontró el usuario: ' + err.message);
  }
}

/* ================================
   TABLA: carga general + filtrado
================================ */
function renderTablaPersonas(personas = []) {
  const tbody = document.querySelector('#tablaDinamica tbody');
  if (!tbody) return;

  tbody.innerHTML = personas.map(p => `
    <tr data-rol="${p.rol}" data-numero="${p.numero}">
      <td>${p.numero ?? ""}</td>
      <td>${p.nombre ?? ""}</td>
      <td>${p.apellidoPaterno ?? ""}</td>
      <td>${p.apellidoMaterno ?? ""}</td>
    </tr>
  `).join("");
}

async function cargarTablaGeneral() {
  try {
    const personas = await fetchJson(`${API_URL}?personas=1`);
    window.__personas = Array.isArray(personas) ? personas : [];
    renderTablaPersonas(window.__personas);
  } catch (err) {
    showMessage('error', 'No se pudo cargar la tabla: ' + err.message);
  }
}

/** Filtra filas por rol: 'Alumno' | 'Auxiliar' | 'Docente' | 'ALL' */
function filtrarTablaPorRol(rol) {
  const rows = document.querySelectorAll('#tablaDinamica tbody tr');
  rows.forEach(tr => {
    const es = tr.dataset.rol === rol || rol === 'ALL';
    tr.style.display = es ? '' : 'none'; // sin depender de CSS .hidden
  });
}

/* ================================
   CREAR / GUARDAR
================================ */
async function onGuardar() {
  if (!validarFormulario()) return;

  const rolSel = $('#ComboTipoRegistro').value;
  const rol = roleValueToName(rolSel);

  const payload = {
    rol,
    nombre: $('#nombre').value.trim(),
    apellidoPaterno: $('#paterno').value.trim(),
    apellidoMaterno: $('#materno').value.trim()
  };

  const num = $('#numeroControl').value.trim();
  const numParsed = num ? (isNaN(Number(num)) ? num : Number(num)) : "";

  if (rol === "Alumno") {
    const idCarr = $('#comboCarreras').value;
    if (!idCarr) return showMessage('warning', "Selecciona una carrera para Alumno.");
    payload.id_Carrera = Number(idCarr);
    if (numParsed !== "") payload.numeroControl = numParsed;
  } else {
    if (numParsed !== "") payload.numeroTrabajador = numParsed;
  }

  try {
    const data = await fetchJson(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });
    showMessage('success', data.mensaje || "Guardado correctamente.");
    limpiarCampos({ limpiarBuscar: true });
    const b = $('#buscar'); if (b) b.focus();

    // refresca tabla
    cargarTablaGeneral();
  } catch (err) {
    showMessage('error', "Error al guardar: " + (err.message || "desconocido"));
  }
}

/* ================================
   ACTUALIZAR
================================ */
async function onModificar() {
  const ultimo = window.__ultimo || {};
  const rol = roleValueToName($('#ComboTipoRegistro').value) || ultimo.rol || "";

  const numInput = $('#numeroControl').value.trim();
  const refNumero = (ultimo.numeroControl ?? ultimo.numeroTrabajador ?? numInput ?? "").toString();
  if (!refNumero) return showMessage('warning', "Primero busca o especifica el número de control/trabajador.");

  const payload = { rol };
  const setTxt = (key, nuevo, actual) => { const v=(nuevo??"").trim(); if(v!=="" && v!==(actual??"")) payload[key]=v; };
  const setInt = (key, nuevo, actual) => { const s=(nuevo??"").trim(); if(s==="")return; const v=isNaN(Number(s))?s:Number(s); if(String(v)!==String(actual??"")) payload[key]=v; };

  setTxt("nombre", $('#nombre').value, ultimo.nombre);
  setTxt("apellidoPaterno", $('#paterno').value, ultimo.apellidoPaterno);
  setTxt("apellidoMaterno", $('#materno').value, ultimo.apellidoMaterno);

  if (rol === "Alumno") {
    const idCarr = $('#comboCarreras').value;
    if (idCarr && String(idCarr) !== String(ultimo.id_Carrera ?? "")) payload.id_Carrera = Number(idCarr);
    setInt("numeroControlNuevo", $('#numeroControl').value, ultimo.numeroControl);
    payload.numeroControl = ultimo.numeroControl ?? (isNaN(Number(refNumero)) ? refNumero : Number(refNumero));
  } else {
    setInt("numeroTrabajadorNuevo", $('#numeroControl').value, ultimo.numeroTrabajador);
    payload.numeroTrabajador = ultimo.numeroTrabajador ?? (isNaN(Number(refNumero)) ? refNumero : Number(refNumero));
  }

  const keys = Object.keys(payload).filter(k => !["numeroControl","numeroTrabajador","rol"].includes(k));
  if (keys.length === 0) return showMessage('info', "No hay cambios para actualizar.");

  try {
    const data = await fetchJson(API_URL, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });
    showMessage('success', data.mensaje || "Actualizado correctamente.");
    limpiarCampos({ limpiarBuscar: true });
    const b = $('#buscar'); if (b) b.focus();
    window.__ultimo = null;

    // refresca tabla
    cargarTablaGeneral();
  } catch (err) {
    showMessage('error', "Error al actualizar: " + (err.message || "desconocido"));
  }
}

/* ================================
   ELIMINAR
================================ */
async function onEliminar() {
  const ultimo = window.__ultimo || {};
  const rol = roleValueToName($('#ComboTipoRegistro').value) || ultimo.rol || "";

  const numInput = $('#numeroControl').value.trim();
  const refNumero = (ultimo.numeroControl ?? ultimo.numeroTrabajador ?? numInput ?? "").toString();
  if (!refNumero) return showMessage('warning', "Ingresa o busca un registro para eliminar.");

  const etiqueta = rol === "Alumno" ? "número de control" : "número de trabajador";
  const ok = await showConfirm(`¿Eliminar registro con ${etiqueta} "${refNumero}"?`, { okText: "Eliminar", cancelText: "Cancelar" });
  if (!ok) return;

  try {
    const data = await fetchJson(API_URL, {
      method: "DELETE",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: rol === "Alumno"
        ? `numeroControl=${encodeURIComponent(refNumero)}`
        : `numeroTrabajador=${encodeURIComponent(refNumero)}`
    });
    showMessage('success', data.mensaje || "Eliminado correctamente.");
    limpiarCampos({ limpiarBuscar: true });
    const b = $('#buscar'); if (b) b.focus();
    window.__ultimo = null;

    // refresca tabla
    cargarTablaGeneral();
  } catch (err) {
    showMessage('error', "Error al eliminar: " + (err.message || "desconocido"));
  }
}

/* ================================
   CAMBIAR CLAVE (DEMO)
================================ */
function onCambiarClave() {
  showMessage('info', '🔒 Cambiar contraseña (demo). Implementa modal o flujo aquí.', { timeout: 2600 });
}

/* ================================
   TABLA: selección + filtrado por rol
================================ */
function wireTablaSeleccion() {
  const tbody = document.querySelector('#tablaDinamica tbody');
  if (!tbody) return;

  tbody.addEventListener('click', (e) => {
    const tr = e.target.closest('tr');
    if (!tr) return;

    // pinta selección
    tbody.querySelectorAll('tr').forEach(r => r.classList.remove('seleccion'));
    tr.classList.add('seleccion');

    // rol y número de la fila
    const rol = tr.dataset.rol;                 // 'Alumno' | 'Auxiliar' | 'Docente'
    const numero = tr.dataset.numero || "";

    // filtra la tabla por el rol de la fila clickeada
    filtrarTablaPorRol(rol);

    // actualiza el combo y campos
    const map = { 'Auxiliar':'1', 'Docente':'2', 'Alumno':'3' };
    const combo = document.getElementById('ComboTipoRegistro');
    combo.value = map[rol] || '1';
    mostrarElemento(); // muestra/oculta carrera si es alumno

    // pone el número en el input (usa el mismo campo para control/trabajador)
    document.getElementById('numeroControl').value = numero;

    // limpia textos (opcional) y enfoca nombre
    ['nombre','paterno','materno'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ""; });
    document.getElementById('nombre').focus();
  });

  // Doble click en el contenedor de la tabla: quita filtro
  document.querySelector('#tabla')?.addEventListener('dblclick', () => {
    filtrarTablaPorRol('ALL');
    // si quieres también resetear el combo:
    // document.getElementById('ComboTipoRegistro').value = '1';
    // mostrarElemento();
  });
}

/* ================================
   INIT
================================ */
function init() {
  mostrarElemento();
  wireInputs();
  cargarCarreras();

  // Cargar toda la gente en la tabla
  cargarTablaGeneral();

  $('#ComboTipoRegistro').addEventListener('change', mostrarElemento);

  const buscar = $("#buscar");
  if (buscar) {
    buscar.addEventListener("keydown", (e) => {
      if (e.key === "Enter") { e.preventDefault(); buscarUsuario(); }
    });
  }

  $('#Guardar')?.addEventListener('click', onGuardar);
  $('#Modificar')?.addEventListener('click', onModificar);
  $('#Eliminar')?.addEventListener('click', onEliminar);
  $('#CambiarClave')?.addEventListener('click', onCambiarClave);

  wireTablaSeleccion();
}

document.addEventListener('DOMContentLoaded', init);
