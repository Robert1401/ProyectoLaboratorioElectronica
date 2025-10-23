// =========================
// Config
// =========================
const API = "/backend/materias.php";
const DEFAULT_CARRERA_ID = 11;

// =========================
const qs  = (s, el=document) => el.querySelector(s);

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

// Confirm modal (si no existe en tu HTML, usa window.confirm)
let __confirmBusy=false;
function showConfirm(text="¿Seguro que deseas continuar?", opts={}){
  const overlay=document.getElementById("confirm");
  const titleEl=document.getElementById("confirm-title");
  const textEl =document.getElementById("confirm-text");
  const btnOK  =document.getElementById("confirm-ok");
  const btnNo  =document.getElementById("confirm-cancel");

  if(!overlay||!titleEl||!textEl||!btnOK||!btnNo){
    return Promise.resolve(window.confirm(text));
  }
  if(__confirmBusy) return Promise.resolve(false);
  __confirmBusy=true;

  titleEl.textContent=opts.title||"Confirmación";
  textEl.textContent =text;
  overlay.hidden=false;
  requestAnimationFrame(()=>{
    overlay.style.animation="overlayIn .18s ease forwards";
    const card=overlay.querySelector(".confirm-card");
    if(card) card.style.animation="cardIn .18s ease forwards";
  });

  return new Promise(resolve=>{
    const cleanup=()=>{
      btnOK.removeEventListener("click",onOK);
      btnNo.removeEventListener("click",onNo);
      window.removeEventListener("keydown",onKey);
      __confirmBusy=false;
    };
    const close=v=>{
      const card=overlay.querySelector(".confirm-card");
      if(card) card.style.animation="cardOut .14s ease forwards";
      overlay.style.animation="overlayOut .14s ease forwards";
      setTimeout(()=>{overlay.hidden=true; cleanup(); resolve(v);},150);
    };
    const onOK=()=>close(true);
    const onNo=()=>close(false);
    const onKey=e=>{ if(e.key==="Escape") close(false); if(e.key==="Enter") close(true); };
    btnOK.addEventListener("click",onOK,{once:true});
    btnNo.addEventListener("click",onNo,{once:true});
    window.addEventListener("keydown",onKey);
  });
}

async function fetchJson(url, options){
  const res = await fetch(url, options);
  let data = {};
  try { data = await res.json(); } catch {}
  if (!res.ok || data.error) throw new Error(data.error || `Error (${res.status})`);
  return data;
}

// ========== normalizador para comparar nombres ==========
const norm = s => (s||"").normalize("NFKC").trim().toLowerCase().replace(/\s+/g," ");

// =========================
// App: Materias
// =========================
(function(){
  const input        = qs('#nombreMateria');
  const btnGuardar   = qs('#Guardar');
  const btnModificar = qs('#Modificar');
  const btnEliminar  = qs('#Eliminar');
  const tbody        = qs('#tbody');
  const tablaVacia   = qs('#tablaVacia');

  if(!input || !btnGuardar || !tbody) return;

  let materias = [];      // [{id_Materia, materia, id_Carrera, id_Estado, ...}]
  let filaSel  = null;
  let nombreOriginal = "";    // para detectar "dirty" en edición
  let purgadaInactivas = false;

  // ======= ESTADOS DE BOTONES =======
  function isDirty(){
    if(!filaSel) return false;
    return norm(input.value) !== norm(nombreOriginal);
  }
  function updateButtonStates(){
    const hayTexto = (input.value||"").trim().length > 0;

    if(!filaSel){
      // Modo NUEVA
      btnGuardar.disabled   = !hayTexto;
      btnModificar.disabled = true;
      btnEliminar.disabled  = true;
      return;
    }
    // Modo EDICIÓN
    const changed = isDirty();
    btnGuardar.disabled   = true;                 // no se guarda en edición
    btnModificar.disabled = !hayTexto || !changed;
    btnEliminar.disabled  = changed;              // bloquear eliminar si hay cambios
    btnEliminar.title     = changed ? "Tienes cambios sin guardar. Actualiza primero." : "";
  }

  // ======= Selección visual y lógica =======
  function marcarSeleccion(tr){
    [...tbody.querySelectorAll("tr")].forEach(r=>r.classList.remove("seleccion"));
    filaSel = tr || null;
    if(filaSel) filaSel.classList.add("seleccion");
    updateButtonStates();
  }
  function limpiarSeleccion(){
    filaSel = null;
    nombreOriginal = "";
    input.value = "";
    marcarSeleccion(null);
  }

  // ======= Render con selección opcional =======
  function render(selectId=null){
    tbody.innerHTML = '';
    if(!materias.length){
      if(tablaVacia) tablaVacia.hidden = false;
      updateButtonStates();
      return;
    }
    if(tablaVacia) tablaVacia.hidden = true;

    materias.forEach(m=>{
      const tr = document.createElement('tr');
      tr.dataset.id        = m.id_Materia;
      tr.dataset.idCarrera = m.id_Carrera;
      tr.dataset.estado    = m.id_Estado; // 1=activo, otros=inactivo
      tr.innerHTML = `<td>${m.materia}</td>`;
      tr.addEventListener('click', ()=>{
        marcarSeleccion(tr);
        input.value = m.materia;
        nombreOriginal = m.materia;
        updateButtonStates(); // en edición: Eliminar ON, Modificar OFF si no cambiaste
      });
      tbody.appendChild(tr);
    });

    // Si nos pasan un id para seleccionar automáticamente (tras guardar)
    if(selectId){
      const tr = [...tbody.querySelectorAll("tr")].find(r=> String(r.dataset.id)===String(selectId));
      if(tr){
        tr.click(); // dispara la misma lógica de selección
      }else{
        updateButtonStates();
      }
    }else{
      updateButtonStates();
    }
  }

  async function cargarMaterias(selectId=null){
    try{
      materias = await fetchJson(API); // GET
      materias.sort((a,b)=> a.materia.localeCompare(b.materia, 'es', {sensitivity:'base'}));
      render(selectId);

      // Si existen inactivas, purgarlas automáticamente una sola vez
      if (!purgadaInactivas && materias.some(m => String(m.id_Estado) !== "1")) {
        purgadaInactivas = true;
        try{
          const r = await fetchJson(`${API}?inactive=1`, { method:"DELETE" });
          if (r.eliminadas > 0) toastSuccess(`Eliminadas ${r.eliminadas} inactivas`);
          // recargar tabla tras purga
          materias = await fetchJson(API);
          materias.sort((a,b)=> a.materia.localeCompare(b.materia, 'es', {sensitivity:'base'}));
          render(selectId);
        }catch(err){
          console.error(err);
          toastError(err.message || "No se pudieron eliminar las inactivas");
        }
      }
    }catch(err){
      toastError(err.message||"No se pudieron cargar materias");
    }
  }

  // ======= INPUT: habilitar Guardar o Modificar según modo =======
  input.addEventListener('input', (e)=>{
    // Sanitizar
    const limpio = e.target.value.replace(/[^\p{L}\p{N}\s\-\.\(\)\/&#]/gu, '');
    if(limpio !== e.target.value) e.target.value = limpio;
    updateButtonStates();
  });
  input.addEventListener('keydown', (e)=>{
    if(e.key === 'Enter'){
      e.preventDefault();
      if(!filaSel) btnGuardar.click();
      else if(isDirty()) btnModificar.click();
    }
  });

  // ======= Guardar =======
  btnGuardar.addEventListener("click", async ()=>{
    if(!input.checkValidity()){ input.reportValidity(); return; }
    const nombre = (input.value||"").trim();
    if(!nombre){ toastInfo("Escribe el nombre de la materia"); return; }

    try{
      const body = JSON.stringify({ nombre, id_Carrera: DEFAULT_CARRERA_ID });
      const data = await fetchJson(API,{
        method:"POST", headers:{ "Content-Type":"application/json" }, body
      });

      toastSuccess("Guardado. Ahora puedes actualizar o eliminar.");
      // recargar y seleccionar automáticamente la nueva materia
      const nuevoId = data.id_Materia;
      await cargarMaterias(nuevoId); // esto hará click en la fila y quedará en edición
      // en edición sin cambios -> Eliminar ON, Modificar OFF
    }catch(err){
      const msg = err?.message || "";
      if (/ya existe/i.test(msg)) showToast({type:"warn", title:"Atención", desc:"Esa materia ya existe en la carrera."});
      else toastError(msg || "No se pudo guardar");
    }
  });

  // ======= Modificar =======
  btnModificar.addEventListener("click", async ()=>{
    if(!filaSel){ toastInfo("Selecciona una materia"); return; }
    const nombre = (input.value||"").trim();
    if(!nombre){ toastInfo("El nombre no puede estar vacío"); return; }

    const id_Materia = parseInt(filaSel.dataset.id, 10);
    const id_Carrera = parseInt(filaSel.dataset.idCarrera||DEFAULT_CARRERA_ID, 10);

    try{
      const body = JSON.stringify({ id_Materia, nombre, id_Carrera });
      const data = await fetchJson(API,{
        method:"PUT", headers:{ "Content-Type":"application/json" }, body
      });

      toastSuccess(data.mensaje || "Materia actualizada");

      // Recargar y volver a modo NUEVA (todo gris)
      await cargarMaterias();
      limpiarSeleccion();
    }catch(err){
      const msg = err?.message || "";
      if (/ya existe/i.test(msg)) showToast({type:"warn", title:"Atención", desc:"Ese nombre ya existe en esa carrera."});
      else toastError(msg || "No se pudo actualizar");
    }
  });

  // ======= Eliminar (bloqueado si hay cambios) =======
  btnEliminar.addEventListener("click", async ()=>{
    if(!filaSel){ toastInfo("Selecciona una materia"); return; }

    if(isDirty()){
      toastWarn("No puedes eliminar: estás editando esta materia. Presiona ACTUALIZAR primero.");
      return;
    }

    const id     = parseInt(filaSel.dataset.id,10);
    const nombre = (filaSel.firstElementChild?.textContent||"").trim();

    const ok = await showConfirm(
      `Se borrará la materia: "${nombre}".\n¿Estás seguro?`,
      { title:"Eliminar materia" }
    );
    if(!ok) return;

    try{
      const url = `${API}?id_Materia=${encodeURIComponent(id)}`;
      const data = await fetchJson(url, { method:"DELETE" });
      showToast({type:"success", title:"Hecho", desc: data.mensaje || `Materia "${nombre}" eliminada`});

      await cargarMaterias();
      limpiarSeleccion(); // volver a modo nueva (todo gris)
    }catch(err){
      toastError(err.message||"No se pudo eliminar");
    }
  });

  // Carga inicial y estados
  cargarMaterias().then(updateButtonStates);
})();
