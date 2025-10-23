/* =========================================================
   MATERIALES (local) — estados correctos de botones
========================================================= */

document.addEventListener("DOMContentLoaded", () => {
  const $  = (s) => document.querySelector(s);
  const $$ = (s) => document.querySelectorAll(s);

  const inputClave  = $("#clave");
  const inputNombre = $("#nombre");
  const stockHint   = $("#stockHint");
  const tbody       = $("#tablaMateriales tbody");

  const btnGuardar    = $("#btnGuardar");
  const btnActualizar = $("#btnActualizar");
  const btnEliminar   = $("#btnEliminar");

  const LS_CATALOGO = "LE_MATERIALES_LOCAL";
  const LS_COMPRAS  = "LE_COMPRAS";
  const LS_MATS_ESPEJO = "LE_materiales";
  const LS_MAT_SEQ     = "LE_material_seq";

  const uid = () => Math.random().toString(36).slice(2, 10);
  const getCat = () => JSON.parse(localStorage.getItem(LS_CATALOGO) || "[]");
  const setCat = (arr) => localStorage.setItem(LS_CATALOGO, JSON.stringify(arr || []));
  const getCompras = () => JSON.parse(localStorage.getItem(LS_COMPRAS) || "[]");

  function getMatsEspejo(){ return JSON.parse(localStorage.getItem(LS_MATS_ESPEJO) || "[]"); }
  function setMatsEspejo(arr){ localStorage.setItem(LS_MATS_ESPEJO, JSON.stringify(arr || [])); }
  function nextMatId(){
    const n = parseInt(localStorage.getItem(LS_MAT_SEQ) || "1", 10);
    localStorage.setItem(LS_MAT_SEQ, String(n + 1));
    return n;
  }

  function syncCatalogToEspejo(){
    const catalogo = getCat();
    const actuales = getMatsEspejo();
    const byName   = new Map(actuales.map(m => [ (m.nombre||"").toLowerCase(), m ]));
    const nuevos = [];
    for (const row of catalogo){
      const nombre = (row.nombre||"").trim();
      if (!nombre) continue;
      const key = nombre.toLowerCase();
      if (byName.has(key)){
        const exist = byName.get(key);
        nuevos.push({ id_Material: exist.id_Material, nombre, cantidad: Number(exist.cantidad)||0 });
        byName.delete(key);
      } else {
        nuevos.push({ id_Material: nextMatId(), nombre, cantidad: 0 });
      }
    }
    setMatsEspejo(nuevos);
  }

  function stockByClave(clave){
    if(!clave) return 0;
    return getCompras()
      .filter(c => (c.clave||"").toLowerCase() === clave.toLowerCase())
      .reduce((acc, c) => acc + (Number(c.cantidad)||0), 0);
  }
  function actualizarHint(){
    const c = (inputClave.value||"").trim();
    if(!c){ stockHint.textContent = "Escribe una clave para ver el stock."; return; }
    const s = stockByClave(c);
    stockHint.textContent = `Stock actual: ${s} ${s===1?'pieza':'piezas'} (según compras)`;
  }

  /* ===== Estado y helpers ===== */
  let idSel = null;
  let original = { clave:"", nombre:"" };
  const norm = s => (s||"").normalize("NFKC").trim().toLowerCase().replace(/\s+/g," ");

  function isDirty(){
    if (idSel == null) return false;
    return norm(inputClave.value)  !== norm(original.clave)
        || norm(inputNombre.value) !== norm(original.nombre);
  }

  function updateButtons(){
    const hasClave  = (inputClave.value||"").trim().length > 0;
    const hasNombre = (inputNombre.value||"").trim().length > 0;
    const readyNew  = hasClave && hasNombre;

    if (idSel == null){
      // NUEVA
      btnGuardar.disabled    = !readyNew;
      btnActualizar.disabled = true;
      btnEliminar.disabled   = true;
      return;
    }
    // EDICIÓN
    const changed = isDirty();
    btnGuardar.disabled    = true;
    btnActualizar.disabled = !readyNew || !changed;
    btnEliminar.disabled   = changed;
    btnEliminar.title      = changed ? "Estás editando. Actualiza primero." : "";
  }

  function limpiarSeleccion(){
    $$("#tablaMateriales tbody tr").forEach(r=>r.classList.remove("seleccionada"));
    idSel = null;
    original = { clave:"", nombre:"" };
    inputClave.value = "";
    inputNombre.value = "";
    actualizarHint();
    updateButtons();
  }

  function seleccionarFila(tr, row){
    $$("#tablaMateriales tbody tr").forEach(r=>r.classList.remove("seleccionada"));
    tr.classList.add("seleccionada");
    idSel = row.id;

    // Seteamos inputs y ORIGINAL antes de calcular botones
    inputClave.value  = row.clave || "";
    inputNombre.value = row.nombre || "";
    original = { clave: inputClave.value, nombre: inputNombre.value };
    actualizarHint();

    // Espera un frame para asegurar que el DOM ya reflejó los valores
    requestAnimationFrame(updateButtons);
  }

  /* ===== Render ===== */
  function render(selectId=null){
    const data = getCat();
    tbody.innerHTML = data.map(row => `
      <tr data-id="${row.id}">
        <td>${row.clave}</td>
        <td>${row.nombre}</td>
      </tr>
    `).join("");

    $$("#tablaMateriales tbody tr").forEach(tr=>{
      tr.addEventListener("click", ()=>{
        const id = tr.dataset.id;
        const row = getCat().find(r=>r.id===id);
        if(!row) return;
        seleccionarFila(tr, row);
      });
    });

    // Seleccionar automáticamente (tras guardar/actualizar) si corresponde
    if (selectId){
      const tr = $(`#tablaMateriales tbody tr[data-id="${selectId}"]`);
      const row = getCat().find(r=>r.id===selectId);
      if (tr && row) seleccionarFila(tr, row);
      else limpiarSeleccion();
    } else {
      updateButtons();
    }

    syncCatalogToEspejo();
  }

  /* ===== Validaciones ===== */
  function leerForm(){
    return {
      clave:  (inputClave.value||"").trim(),
      nombre: (inputNombre.value||"").trim()
    };
  }
  function validar({clave, nombre}){
    if(!clave)  return "Ingresa la clave.";
    if(!nombre) return "Ingresa el nombre.";
    return null;
  }

  /* ===== Acciones ===== */
  btnGuardar?.addEventListener("click", ()=>{
    const form = leerForm();
    const err = validar(form);
    if(err){ alert(err); return; }

    const data = getCat();
    if(data.some(x=>x.clave.toLowerCase()===form.clave.toLowerCase())){
      alert("Esa clave ya existe en el catálogo.");
      return;
    }

    const id = uid();
    data.push({ id, ...form });
    setCat(data);

    // Refresca y SELECCIONA el nuevo → edición base (Eliminar ON, Actualizar gris)
    render(id);
    alert("✅ Guardado. Ahora puedes actualizar o eliminar.");
  });

  btnActualizar?.addEventListener("click", ()=>{
    if (idSel == null){ alert("Selecciona un material para actualizar."); return; }

    const form = leerForm();
    const err = validar(form);
    if(err){ alert(err); return; }

    const data = getCat();
    const idx = data.findIndex(x=>x.id===idSel);
    if(idx<0){ alert("No se encontró el registro seleccionado."); return; }

    const cambiandoClave = data[idx].clave.toLowerCase() !== form.clave.toLowerCase();
    if(cambiandoClave && data.some(x=>x.id!==idSel && x.clave.toLowerCase()===form.clave.toLowerCase())){
      alert("La nueva clave ya existe en otro material.");
      return;
    }

    data[idx] = { ...data[idx], ...form };
    setCat(data);

    // Re-render y RE-SELECCIONA el mismo → Actualizar queda GRIS, Eliminar ON
    render(idSel);
    // al re-seleccionar, seleccionarFila fija original = inputs (no dirty)
    alert("✏️ Material actualizado.");
  });

  btnEliminar?.addEventListener("click", ()=>{
    if (idSel == null){ alert("Selecciona un material para eliminar."); return; }
    if (isDirty()){
      alert("No puedes eliminar: estás editando este material. Presiona ACTUALIZAR primero.");
      return;
    }

    const fila = $(`#tablaMateriales tbody tr.seleccionada`);
    const nombreSel = fila ? fila.children[1].textContent.trim() : "este material";
    if(!confirm(`Se eliminará: "${nombreSel}". ¿Estás seguro?`)) return;

    const data = getCat().filter(x=>x.id!==idSel);
    setCat(data);

    render();            // refresca tabla
    limpiarSeleccion();  // vuelve a modo nueva (todo gris)
    alert("🗑️ Material eliminado.");
  });

  /* ===== Eventos de input → refresco inmediato ===== */
  const refreshNow = () => { actualizarHint(); updateButtons(); };
  ["input","keyup","change"].forEach(ev=>{
    inputClave.addEventListener(ev, refreshNow);
    inputNombre.addEventListener(ev, updateButtons);
  });

  /* ===== Init ===== */
  render();
  actualizarHint();
  updateButtons();
});
