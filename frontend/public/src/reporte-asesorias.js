/* =====================================
   Reporte de Asesorías (localStorage)
   - Lee "asesorias"
   - Rellena filtros (Auxiliar/Título)
   - Render tabla con filtros
   - Exporta CSV (Excel) y PDF (print)
===================================== */

(function(){
  const $ = (s)=> document.querySelector(s);

  const LS_KEY = "asesorias";   // mismo que usa tu módulo de gestión

  /* ---------- DAO ---------- */
  function getAll(){
    try { return JSON.parse(localStorage.getItem(LS_KEY) || "[]"); }
    catch { return []; }
  }

  /* ---------- Render filtros ---------- */
  function fillFilters(data){
    const auxSel   = $("#filtroAuxiliar");
    const tituloSel= $("#filtroTitulo");
    if (!auxSel || !tituloSel) return;

    const auxs = [...new Set(data.map(x => (x.auxiliar||"").trim()).filter(Boolean))].sort();
    const tits = [...new Set(data.map(x => (x.titulo||"").trim()).filter(Boolean))].sort();

    auxSel.innerHTML = `<option value="">Todos los auxiliares</option>` +
      auxs.map(a => `<option value="${escapeHTML(a)}">${escapeHTML(a)}</option>`).join("");

    tituloSel.innerHTML = `<option value="">Todos los títulos</option>` +
      tits.map(t => `<option value="${escapeHTML(t)}">${escapeHTML(t)}</option>`).join("");
  }

  /* ---------- Render tabla ---------- */
  function renderTable(data){
    const tbody = $("#tablaAsesorias");
    if (!tbody) return;

    if (!data.length){
      tbody.innerHTML = `<tr><td colspan="6" style="padding:14px;text-align:center;opacity:.7;">Sin registros</td></tr>`;
      return;
    }

    tbody.innerHTML = data.map(a => `
      <tr>
        <td>${escapeHTML(a.titulo || "")}</td>
        <td>${escapeHTML(a.auxiliar || "")}</td>
        <td>${escapeHTML(a.fecha || "")}</td>
        <td>${escapeHTML(a.hora || "")}</td>
        <td style="text-align:left">${escapeHTML(a.descripcion || "")}</td>
        <td>${Number(a.cupoActual || 0)}/${Number(a.cupoTotal || 0)}</td>
      </tr>
    `).join("");
  }

  /* ---------- Filtros ---------- */
  function applyFilters(){
    const aux = $("#filtroAuxiliar")?.value || "";
    const tit = $("#filtroTitulo")?.value || "";

    const all = getAll();
    const filtered = all.filter(a => {
      const byAux = !aux || (a.auxiliar||"") === aux;
      const byTit = !tit || (a.titulo||"") === tit;
      return byAux && byTit;
    });

    renderTable(filtered);
    safeInfo("Filtros aplicados.");
  }

  function clearFilters(){
    $("#filtroAuxiliar").value = "";
    $("#filtroTitulo").value = "";
    renderTable(getAll());
    safeInfo("Filtros limpiados.");
  }

  /* ---------- Exportar ---------- */
  function descargarExcel(){
    const rows = [["Título","Auxiliar","Fecha","Hora","Descripción","Cupo"]];
    getAll().forEach(a => {
      rows.push([
        a.titulo||"", a.auxiliar||"", a.fecha||"", a.hora||"",
        (a.descripcion||"").replace(/\s+/g," ").trim(),
        `${Number(a.cupoActual||0)}/${Number(a.cupoTotal||0)}`
      ]);
    });

    const csv = rows.map(r =>
      r.map(v => {
        const s = String(v ?? "");
        return /[",;\n]/.test(s) ? `"${s.replace(/"/g,'""')}"` : s;
      }).join(",")
    ).join("\n");

    const blob = new Blob([csv], {type:"text/csv;charset=utf-8;"});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement("a");
    a.href = url; a.download = "reporte-asesorias.csv";
    document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);

    safeOk("Descargando asesorías (CSV).");
  }

  function descargarPDF(){
    const all = getAll();

    const rows = all.map(a => `
      <tr>
        <td style="border:1px solid #000;padding:8px;">${escapeHTML(a.titulo||"")}</td>
        <td style="border:1px solid #000;padding:8px;">${escapeHTML(a.auxiliar||"")}</td>
        <td style="border:1px solid #000;padding:8px;text-align:center;">${escapeHTML(a.fecha||"")}</td>
        <td style="border:1px solid #000;padding:8px;text-align:center;">${escapeHTML(a.hora||"")}</td>
        <td style="border:1px solid #000;padding:8px;text-align:left;">${escapeHTML(a.descripcion||"")}</td>
        <td style="border:1px solid #000;padding:8px;text-align:center;">${Number(a.cupoActual||0)}/${Number(a.cupoTotal||0)}</td>
      </tr>
    `).join("");

    const win = window.open("", "_blank");
    win.document.write(`
      <!DOCTYPE html><html><head><meta charset="utf-8">
      <title>Reporte de Asesorías</title>
      <style>
        body{ font-family:Segoe UI,Arial; padding:20px; }
        h1{ text-align:center; margin:0 0 14px; }
        .line{ height:3px; background:#000; margin-bottom:12px; }
        table{ width:100%; border-collapse:collapse; }
        th{ background:#000; color:#fff; padding:10px; border:1px solid #000; }
        td{ border:1px solid #000; padding:8px; }
      </style>
      </head><body>
        <h1>Reporte de Asesorías</h1>
        <div class="line"></div>
        <table>
          <thead>
            <tr>
              <th>Título</th><th>Auxiliar</th><th>Fecha</th><th>Hora</th><th>Descripción</th><th>Cupo</th>
            </tr>
          </thead>
          <tbody>${rows || `<tr><td colspan="6" style="text-align:center">Sin registros</td></tr>`}</tbody>
        </table>
        <script>window.addEventListener('load', ()=> setTimeout(()=>window.print(), 150));<\/script>
      </body></html>
    `);
    win.document.close();

    safeOk("Abriendo vista de impresión (guárdalo como PDF).");
  }

  /* ---------- Utilidades ---------- */
  function escapeHTML(s){ return String(s).replace(/[&<>"']/g, c=>({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[c])); }

  // notify helpers (si no está, usa console)
  const hasNotify = typeof window.notify?.show === "function";
  const safeOk   = (m)=> hasNotify ? notify.show(m,"success",{title:"¡Listo!"}) : console.log(m);
  const safeInfo = (m)=> hasNotify ? notify.show(m,"info") : console.log(m);

  /* ---------- Nav ---------- */
  function initNav(){
    $("#btnBack")?.addEventListener("click", () => {
      if (history.length > 1) history.back();
      else location.href = "../index.html";
    });
  }

  /* ---------- Boot ---------- */
  document.addEventListener("DOMContentLoaded", ()=>{
    const all = getAll();
    fillFilters(all);
    renderTable(all);

    $("#btnAplicar")?.addEventListener("click", applyFilters);
    $("#btnLimpiar")?.addEventListener("click", clearFilters);
    $("#btnExcel")?.addEventListener("click", descargarExcel);
    $("#btnPDF")?.addEventListener("click", descargarPDF);

    initNav();
  });
})();
