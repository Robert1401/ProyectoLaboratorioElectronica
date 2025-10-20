// =========================
// Config
// =========================
const API = "/backend/materias.php";   // Ajusta si tu ruta es otra
const DEFAULT_CARRERA_ID = 11;         // Carrera por defecto (p.ej. 11 = Ing. Electrónica)

// =========================
// Utilidades DOM y UI
// =========================
const qs  = (s, el=document) => el.querySelector(s);
const qsa = (s, el=document) => [...el.querySelectorAll(s)];

const toast = (msg)=>{
  const el = qs('#toastCorner');
  if(!el) return;
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(el._t);
  el._t = setTimeout(()=> el.classList.remove('show'), 1800);
};

let __toastTimer=null;
function showToast({title="",desc="",type="info",duration=2200}={}){
  const host=document.getElementById("toastOverlay"); if(!host) return;
  host.innerHTML=`
    <div class="toast-card ${type}">
      <div class="toast-icon">${type==="success"?"✅":type==="error"?"❌":type==="warn"?"⚠️":"ℹ️"}</div>
      ${title?`<h4 class="title">${title}</h4>`:""}
      ${desc?`<p class="desc">${desc}</p>`:""}
    </div>`;
  host.hidden=false;
  clearTimeout(__toastTimer);
  const close=()=>{
    const c=host.querySelector(".toast-card"); if(c) c.style.animation="toastOut .16s ease forwards";
    host.style.animation="toastOverlayOut .16s ease forwards";
    setTimeout(()=>{host.hidden=true; host.innerHTML=""; host.style.animation="";},170);
  };
  host.onclick=close; __toastTimer=setTimeout(close,duration);
}
const toastSuccess = msg => showToast({title:"¡Listo!",   desc:msg, type:"success"});
const toastError   = msg => showToast({title:"Ups",       desc:msg, type:"error"});
const toastInfo    = msg => showToast({title:"Aviso",     desc:msg, type:"info"});
const toastWarn    = msg => showToast({title:"Atención",  desc:msg, type:"warn"});

// ---- Confirm modal robusto (fix de “pantalla negra”) ----
let __confirmBusy = false;
function showConfirm(text="¿Seguro que deseas continuar?", opts={}){
  const overlay=document.getElementById("confirm");
  const titleEl=document.getElementById("confirm-title");
  const textEl =document.getElementById("confirm-text");
  const btnOK  =document.getElementById("confirm-ok");
  const btnNo  =document.getElementById("confirm-cancel");

  if(!overlay||!titleEl||!textEl||!btnOK||!btnNo){
    return Promise.resolve(window.confirm(text));
  }

  if (__confirmBusy) return Promise.resolve(false);
  __confirmBusy = true;

  titleEl.textContent=opts.title||"Confirmación";
  textEl.textContent =text;

  // resetear animaciones antes de abrir
  overlay.style.animation = "";
  const card = overlay.querySelector(".confirm-card");
  if (card) card.style.animation = "";

  overlay.hidden=false;

  // forzar animaciones de entrada (evita quedarse con cardOut)
  requestAnimationFrame(()=>{
    overlay.style.animation="overlayIn .18s ease forwards";
    if (card) card.style.animation="cardIn .18s ease forwards";
  });

  return new Promise(resolve=>{
    const cleanup = ()=>{
      btnOK.removeEventListener("click",onOK);
      btnNo.removeEventListener("click",onNo);
      window.removeEventListener("keydown",onKey);
      __confirmBusy = false;
    };
    const close=v=>{
      // animaciones de salida
      if (card) card.style.animation="cardOut .14s ease forwards";
      overlay.style.animation="overlayOut .14s ease forwards";
      setTimeout(()=>{
        overlay.hidden=true;
        overlay.style.animation="";
        if (card) card.style.animation=""; // importante: limpiar para el siguiente open
        cleanup();
        resolve(v);
      },150);
    };
    const onOK =()=>close(true);
    const onNo =()=>close(false);
    const onKey=e=>{ if(e.key==="Escape") close(false); if(e.key==="Enter") close(true); };
    btnOK.addEventListener("click",onOK,{once:true});
    btnNo.addEventListener("click",onNo,{once:true});
    window.addEventListener("keydown",onKey);
  });
}

function normalizeName(s){
  return (s||"")
    .normalize("NFKD")
    .replace(/[\u0300-\u036f]/g,"")
    .toLowerCase()
    .trim()
    .replace(/\s+/g," ");
}

async function fetchJson(url, options){
  const res = await fetch(url, options);
  let data = {};
  try { data = await res.json(); } catch {}
  if (!res.ok || data.error) {
    throw new Error(data.error || `Error (${res.status})`);
  }
  return data;
}

// =========================
// App: Materias (vinculada a BD)
// =========================
(function(){
  const input        = qs('#nombreMateria');
  const btnGuardar   = qs('#Guardar');
  const btnModificar = qs('#Modificar');
  const btnEliminar  = qs('#Eliminar');
  const tbody        = qs('#tbody');
  const tablaVacia   = qs('#tablaVacia');

  if(!input || !btnGuardar || !tbody) return;

  // Estado
  let materias = [];   // [{id_Materia, materia, id_Carrera, id_Estado, carrera}]
  let filaSel  = null; // <tr> seleccionada

  // ------- Helpers internos -------
  function handleDupOrError(err, accion="operación"){
    const msg = err?.message || "";
    if (/ya existe/i.test(msg)) {
      toastWarn("Esa materia ya existe en la carrera.");
    } else {
      toastError(msg || `No se pudo completar la ${accion}`);
    }
  }

  function marcarSeleccion(tr){
    if(filaSel) filaSel.classList.remove("seleccion");
    filaSel = tr || null;
    if(filaSel) filaSel.classList.add("seleccion");
    btnModificar.disabled = !filaSel;
    btnEliminar.disabled  = !filaSel;
  }

  // ------- Render -------
  function render(){
    tbody.innerHTML = '';
    if(!materias.length){
      if(tablaVacia) tablaVacia.hidden = false;
      btnModificar.disabled = true;
      btnEliminar.disabled  = true;
      return;
    }
    if(tablaVacia) tablaVacia.hidden = true;

    materias.forEach(m=>{
      const tr = document.createElement('tr');

      const activo = String(m.id_Estado) === "1";
      tr.dataset.id        = m.id_Materia;
      tr.dataset.idCarrera = m.id_Carrera;
      tr.dataset.activo    = activo ? "1" : "0";

      tr.classList.toggle("activa",   activo);
      tr.classList.toggle("inactiva", !activo);

      tr.innerHTML = `<td>${m.materia}</td>`;

      tr.addEventListener('click', onRowClick);
      tr.addEventListener('dblclick', onRowDblClick);
      tbody.appendChild(tr);
    });
  }

  function onRowClick(e){
    const tr=e.currentTarget;
    marcarSeleccion(tr);
    input.value = tr.firstElementChild.textContent.trim();

    // Si la fila es INACTIVA, ofrecer activarla
    if(tr.dataset.activo==="0"){
      const nombre = input.value;
      showConfirm(
        `La materia "${nombre}" está INACTIVA.\n¿Deseas ACTIVARLA ahora?`,
        { title:'Reactivar materia' }
      ).then(ok=> ok && toggleEstadoSeleccionado(true));
    }
  }

  function onRowDblClick(e){
    const tr=e.currentTarget;
    marcarSeleccion(tr);
    toggleEstadoSeleccionado(); // alterna estado (con confirm interno)
  }

  // ------- Cargar de BD -------
  async function cargarMaterias(){
    try{
      materias = await fetchJson(API); // GET lista
      // Ordena por nombre usando locale ES y sin acentos
      materias.sort((a,b)=> a.materia.localeCompare(b.materia, 'es', {sensitivity:'base'}));
      render();
    }catch(err){
      toastError(err.message||"No se pudieron cargar materias");
    }
  }

  // ------- Guardar -------
  btnGuardar.addEventListener("click", async ()=>{
    if(!input.checkValidity()){ input.reportValidity(); return; }
    const nombre = (input.value||"").trim();
    if(!nombre){ toastInfo("Escribe el nombre de la materia"); return; }

    try{
      const body = JSON.stringify({ nombre, id_Carrera: DEFAULT_CARRERA_ID });
      const data = await fetchJson(API,{
        method:"POST", headers:{ "Content-Type":"application/json" }, body
      });

      toastSuccess(data.mensaje || "Materia guardada");
      input.value="";
      marcarSeleccion(null);
      await cargarMaterias();
    }catch(err){
      handleDupOrError(err, "guardado");
    }
  });

  // ------- Modificar -------
  btnModificar.addEventListener("click", async ()=>{
    if(!filaSel){ toastInfo("Selecciona una materia"); return; }
    if(!input.value.trim()){ toastInfo("El nombre no puede estar vacío"); return; }

    const id_Materia = parseInt(filaSel.dataset.id, 10);
    const nombre     = (input.value||"").trim();
    const id_Carrera = parseInt(filaSel.dataset.idCarrera||DEFAULT_CARRERA_ID, 10);

    try{
      const body = JSON.stringify({ id_Materia, nombre, id_Carrera });
      const data = await fetchJson(API,{
        method:"PUT", headers:{ "Content-Type":"application/json" }, body
      });

      toastSuccess(data.mensaje || "Materia actualizada");
      input.value="";
      marcarSeleccion(null);
      await cargarMaterias();
    }catch(err){
      handleDupOrError(err, "actualización");
    }
  });

  // ------- Eliminar (dar de baja / reactivar) -------
  // Confirmación se hace DENTRO de toggleEstadoSeleccionado()
  btnEliminar.addEventListener("click", async ()=>{
    if(!filaSel){ toastInfo("Selecciona una materia"); return; }
    await toggleEstadoSeleccionado();
  });

  // ------- Alternar estado (con opción de forzar activar) -------
  async function toggleEstadoSeleccionado(forceActivate=false){
    if(!filaSel){ toastInfo("Selecciona una materia"); return; }
    const id  = parseInt(filaSel.dataset.id,10);
    const act = filaSel.dataset.activo==="1";
    const nombre = (filaSel.firstElementChild?.textContent||"").trim();

    // Confirm interno si no viene "forzar"
    if(!forceActivate){
      const msg   = act
        ? `¿Deseas DAR DE BAJA la materia "${nombre}"?\nQuedará INACTIVA (aparecerá subrayada).`
        : `La materia "${nombre}" está INACTIVA.\n¿Deseas ACTIVARLA ahora?`;
      const title = act ? "Dar de baja" : "Activar";
      const ok = await showConfirm(msg,{title});
      if(!ok) return;
    }

    try{
      const set_activo = forceActivate ? 1 : (act ? 0 : 1);

      // Actualiza visual AL INSTANTE
      const nuevoActivo = String(set_activo);
      const isActive    = (nuevoActivo === "1");
      filaSel.dataset.activo = nuevoActivo;
      filaSel.classList.toggle("activa",   isActive);
      filaSel.classList.toggle("inactiva", !isActive);

      // Persiste en servidor
      const body = JSON.stringify({ id_Materia:id, set_activo });
      const data = await fetchJson(API,{
        method:"PUT", headers:{ "Content-Type":"application/json" }, body
      });

      toastSuccess(data.mensaje || (set_activo? "Materia activada" : "Materia inactivada"));

      // Recarga desde BD para quedar 100% sincronizado
      await cargarMaterias();
    }catch(err){
      toastError(err.message||"No se pudo cambiar el estado");
      await cargarMaterias(); // Revertir visual si falló
    }
  }

  // ------- Validación de caracteres y Enter=Guardar -------
  // Permite letras (con acentos), números, espacios y - . ( ) / & #
  input.addEventListener('input', (e)=>{
    const limpio = e.target.value.replace(/[^\p{L}\p{N}\s\-\.\(\)\/&#]/gu, '');
    if(limpio !== e.target.value) e.target.value = limpio;
  });
  input.addEventListener('keydown', (e)=>{
    if(e.key === 'Enter'){ e.preventDefault(); btnGuardar.click(); }
  });

  // Carga inicial
  cargarMaterias();
})();
