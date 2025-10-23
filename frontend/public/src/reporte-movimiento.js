/* =========================================================
   Reporte: Movimiento del material por carrera
   - Fuente: localStorage["LE_movimientos"]
   - Filtros: Carrera, Material
   - Exportar: CSV (Excel) y PDF (print)
   - Si no hay datos, se genera una semilla demo.
========================================================= */

(function(){
  const $ = (s)=>document.querySelector(s);
  const LS_KEY = "LE_movimientos";

  /* ---------- Seed (si no hay nada) ---------- */
  function seedIfEmpty(){
    if (localStorage.getItem(LS_KEY)) return;
    const demo = [
      { material:"Protoboard 830 puntos", carrera:"Sistemas", cantidad:5,  tipo:"Salida", fecha:"2025-10-18", usuario:"20400123" },
      { material:"Resistor 220Ω",         carrera:"Electrónica", cantidad:40, tipo:"Entrada", fecha:"2025-10-19", usuario:"Almacén" },
      { material:"Capacitor 10µF",        carrera:"Sistemas", cantidad:12, tipo:"Salida", fecha:"2025-10-20", usuario:"20400119" },
      { material:"Cable Dupont (40p)",    carrera:"Mecatrónica", cantidad:6,  tipo:"Salida", fecha:"2025-10-21", usuario:"20400222" },
      { material:"Protoboard 830 puntos", carrera:"Electrónica", cantidad:10, tipo:"Entrada", fecha:"2025-10-21", usuario:"Almacén" }
    ];
    localStorage.setItem(LS_KEY, JSON.stringify(demo));
  }

  /* ---------- DAO ---------- */
  function getAll(){
    try { return JSON.parse(localStorage.getItem(LS_KEY)||"[]"); }
    catch { return []; }
  }

  /* ---------- Filtros dinámicos ---------- */
  function fillFilters(data){
    const carreras = [...new Set(data.map(x => (x.carrera||"").trim()).filter(Boolean))].sort();
    const materiales = [...new Set(data.map(x => (x.material||"").trim()).filter(Boolean))].sort();

    $("#filtroCarrera").innerHTML =
      `<option value="">Todas</option>` +
      carreras.map(c=>`<option value="${escapeHTML(c)}">${escapeHTML(c)}</option>`).join("");

    $("#filtroMaterial").innerHTML =
      `<option value="">Todos</option>` +
      materiales.map(m=>`<option value="${escapeHTML(m)}">${escapeHTML(m)}</option>`).join("");
  }

  /* ---------- Tabla ---------- */
  function renderTable(rows){
    const tb = $("#tablaMovimiento");
    if (!rows.length){
      tb.innerHTML = `<tr><td colspan="6" style="padding:14px;text-align:center;opacity:.7;">Sin registros</td></tr>`;
      return;
    }
    tb.innerHTML = rows.map(r=>`
      <tr>
        <td>${escapeHTML(r.material||"")}</td>
        <td>${escapeHTML(r.carrera||"")}</td>
        <td>${Number(r.cantidad||0)}</td>
        <td>${escapeHTML(r.tipo||"")}</td>
        <td>${escapeHTML(r.fecha||"")}</td>
        <td>${escapeHTML(r.usuario||"")}</td>
      </tr>
    `).join("");
  }

  /* ---------- Aplicar/Limpiar ---------- */
  function applyFilters(){
    const c = $("#filtroCarrera").value;
    const m = $("#filtroMaterial").value;
    const filtered = getAll().filter(r => {
      const okC = !c || (r.carrera||"") === c;
      const okM = !m || (r.material||"") === m;
      return okC && okM;
    });
    renderTable(filtered);
    toastInfo("Filtros aplicados.");
  }
  function clearFilters(){
    $("#filtroCarrera").value = "";
    $("#filtroMaterial").value = "";
    renderTable(getAll());
    toastInfo("Filtros limpiados.");
  }

  /* ---------- Export: CSV ---------- */
  function descargarExcel(){
    const data = getAll();
    const head = ["Material","Carrera","Cantidad","Tipo","Fecha","Usuario"];
    const rows = [head].concat(
      data.map(r => [
        r.material||"", r.carrera||"", Number(r.cantidad||0),
        r.tipo||"", r.fecha||"", r.usuario||""
      ])
    );
    const csv = rows.map(r => r.map(cell => {
      const s = String(cell ?? "");
      return /[",;\n]/.test(s) ? `"${s.replace(/"/g,'""')}"` : s;
    }).join(",")).join("\n");

    const blob = new Blob([csv], {type:"text/csv;charset=utf-8;"});
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url; a.download = "reporte-movimiento.csv";
    document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
    toastOk("Descargando movimientos (CSV).");
  }

  /* ---------- Export: PDF (print) ---------- */
  function descargarPDF(){
    const data = getAll();
    const rows = data.map(r=>`
      <tr>
        <td style="border:1px solid #000;padding:8px;">${escapeHTML(r.material||"")}</td>
        <td style="border:1px solid #000;padding:8px;">${escapeHTML(r.carrera||"")}</td>
        <td style="border:1px solid #000;padding:8px;text-align:center;">${Number(r.cantidad||0)}</td>
        <td style="border:1px solid #000;padding:8px;">${escapeHTML(r.tipo||"")}</td>
        <td style="border:1px solid #000;padding:8px;text-align:center;">${escapeHTML(r.fecha||"")}</td>
        <td style="border:1px solid #000;padding:8px;">${escapeHTML(r.usuario||"")}</td>
      </tr>
    `).join("");

    const win = window.open("", "_blank");
    win.document.write(`
      <!DOCTYPE html><html><head><meta charset="utf-8">
      <title>Movimiento del material por carrera</title>
      <style>
        body{ font-family:Segoe UI,Arial; padding:20px; }
        h1{ text-align:center; margin:0 0 14px; }
        .line{ height:3px; background:#000; margin-bottom:12px; }
        table{ width:100%; border-collapse:collapse; }
        th{ background:#000; color:#fff; padding:10px; border:1px solid #000; }
        td{ border:1px solid #000; padding:8px; }
      </style>
      </head><body>
        <h1>Movimiento del material por carrera</h1>
        <div class="line"></div>
        <table>
          <thead>
            <tr>
              <th>Material</th><th>Carrera</th><th>Cantidad</th><th>Tipo</th><th>Fecha</th><th>Usuario</th>
            </tr>
          </thead>
          <tbody>${rows || `<tr><td colspan="6" style="text-align:center">Sin registros</td></tr>`}</tbody>
        </table>
        <script>window.addEventListener('load', ()=> setTimeout(()=>window.print(), 150));<\/script>
      </body></html>
    `);
    win.document.close();
    toastOk("Abriendo vista de impresión (guárdalo como PDF).");
  }

  /* ---------- Utilidades ---------- */
  function escapeHTML(s){ return String(s).replace(/[&<>"']/g, c=>({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[c])); }
  const hasNotify = typeof window.notify?.show === "function";
  const toastOk   = (m)=> hasNotify ? notify.show(m,"success",{title:"¡Listo!"}) : console.log(m);
  const toastInfo = (m)=> hasNotify ? notify.show(m,"info") : console.log(m);

  /* ---------- Nav ---------- */
  function initNav(){
    $("#btnBack")?.addEventListener("click", ()=>{
      if (history.length > 1) history.back();
      else location.href = "../index.html";
    });
  }

  /* ---------- Boot ---------- */
  document.addEventListener("DOMContentLoaded", ()=>{
    seedIfEmpty();
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
