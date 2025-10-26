/* ================================
   Reporte de Inventario (local)
   - Lee “LE_materiales” y “auxiliarNombre”
   - Rellena tabla
   - Exporta CSV (Excel) y PDF (print)
   - Claves por nombre (fallback) o autogeneradas
================================== */

(function () {
  const $ = (s) => document.querySelector(s);

  // Claves usadas en tus otras pantallas
  const LS = {
    materiales: "LE_materiales",      // [{id_Material, nombre, cantidad, (opcional) clave}]
    auxiliar:   "auxiliarNombre"      // string
  };

  /* ---------- Mapeo de claves por nombre (tus ejemplos) ---------- */
  const CLAVES_POR_NOMBRE = {
    "Capacitor Electrolítico 10µF": "CAP-12",
    "Protoboard 830 puntos":        "PRO-12",
    "Resistor 220Ω":                "R-2343",
    "Cable Dupont M-M":             "M-M-34"
  };

  /* ---------- DAO ---------- */
  function getMats() {
    try { return JSON.parse(localStorage.getItem(LS.materiales) || "[]"); }
    catch { return []; }
  }
  function setMats(arr) {
    localStorage.setItem(LS.materiales, JSON.stringify(arr || []));
  }
  function getAux() {
    return localStorage.getItem(LS.auxiliar) || "Auxiliar";
  }

  /* ---------- Claves ---------- */
  function autoClave(nombre = "", id) {
    // Iniciales (2 grupos) + 3 dígitos estables
    const clean = nombre.toUpperCase().replace(/[^A-Z0-9\s-]/g, " ").trim();
    const tokens = clean.split(/\s+/).filter(Boolean);
    const a = (tokens[0] || "X")[0];
    const b = (tokens[1] || tokens[0] || "X")[0];
    const num = (Number(id) || clean.length * 37).toString().padStart(3, "0").slice(-3);
    return `${a}-${b}-${num}`;
  }

  function claveParaMaterial(m) {
    if (m?.clave && String(m.clave).trim()) return String(m.clave).trim();
    const nombre = m?.nombre || "";
    if (CLAVES_POR_NOMBRE[nombre]) return CLAVES_POR_NOMBRE[nombre];
    return autoClave(nombre, m?.id_Material);
  }

  // (Opcional) Si quieres persistir la clave calculada en localStorage, deja en true
  const PERSISTIR_CLAVE_FALTANTE = true;
  function sincronizarClavesEnStorage() {
    if (!PERSISTIR_CLAVE_FALTANTE) return;
    const mats = getMats();
    let cambiado = false;
    for (const m of mats) {
      if (!m.clave || !String(m.clave).trim()) {
        const c = claveParaMaterial(m);
        if (c) { m.clave = c; cambiado = true; }
      }
    }
    if (cambiado) setMats(mats);
  }

  /* ---------- Render Tabla ---------- */
  function renderTabla() {
    const tbody = $("#tablaInventario");
    if (!tbody) return;

    const mats = getMats();
    const aux = getAux();

    if (!mats.length) {
      tbody.innerHTML = `<tr><td colspan="4" style="padding:14px;text-align:center;opacity:.7;">Sin materiales</td></tr>`;
      return;
    }

    tbody.innerHTML = mats.map(m => {
      const clave = claveParaMaterial(m);
      return `
        <tr>
          <td>${escapeHTML(m.nombre || `Material #${m.id_Material}`)}</td>
          <td>${escapeHTML(clave)}</td>
          <td>${Number(m.cantidad || 0)}</td>
          <td>${escapeHTML(aux)}</td>
        </tr>
      `;
    }).join("");
  }

  /* ---------- Utilidades ---------- */
  function escapeHTML(s) {
    return String(s).replace(/[&<>"']/g, (c)=>({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[c]));
  }

  /* ---------- Descargas ---------- */
  function descargarExcel() {
    const mats = getMats();
    const aux  = getAux();

    const rows = [
      ["Nombre material","Clave","Cantidad","Usuario"],
      ...mats.map(m => [
        m.nombre || `Material #${m.id_Material}`,
        claveParaMaterial(m),
        Number(m.cantidad || 0),
        aux
      ])
    ];

    const csv = rows.map(r =>
      r.map(v => {
        const s = String(v ?? "");
        return /[",;\n]/.test(s) ? `"${s.replace(/"/g,'""')}"` : s;
      }).join(",")
    ).join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement("a");
    a.href = url; a.download = "reporte-inventario.csv";
    document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);

    safeOk("Descargando inventario (CSV).");
  }

  function descargarPDF() {
    const aux  = getAux();
    const mats = getMats();

    const rows = mats.map(m => `
      <tr>
        <td style="border:1px solid #000;padding:8px;">${escapeHTML(m.nombre || `Material #${m.id_Material}`)}</td>
        <td style="border:1px solid #000;padding:8px;text-align:center;">${escapeHTML(claveParaMaterial(m))}</td>
        <td style="border:1px solid #000;padding:8px;text-align:center;">${Number(m.cantidad || 0)}</td>
        <td style="border:1px solid #000;padding:8px;">${escapeHTML(aux)}</td>
      </tr>
    `).join("");

    const win = window.open("", "_blank");
    win.document.write(`
      <!DOCTYPE html><html><head><meta charset="utf-8">
      <title>Reporte de Inventario</title>
      <style>
        body{ font-family:Segoe UI,Arial; padding:20px; }
        h1{ text-align:center; margin:0 0 14px; }
        .line{ height:3px; background:#000; margin-bottom:12px; }
        table{ width:100%; border-collapse:collapse; }
        th{ background:#000; color:#fff; padding:10px; border:1px solid #000; }
        td{ border:1px solid #000; padding:8px; }
      </style>
      </head><body>
        <h1>Reporte de Inventario</h1>
        <div class="line"></div>
        <table>
          <thead>
            <tr>
              <th>Nombre material</th><th>Clave</th><th>Cantidad</th><th>Usuario</th>
            </tr>
          </thead>
          <tbody>${rows || `<tr><td colspan="4" style="text-align:center">Sin materiales</td></tr>`}</tbody>
        </table>
        <script>window.addEventListener('load', ()=> setTimeout(()=>window.print(), 150));<\/script>
      </body></html>
    `);
    win.document.close();

    safeOk("Abriendo vista de impresión (guárdalo como PDF).");
  }

  /* ---------- Navegación ---------- */
  function initNav() {
    $("#btnBack")?.addEventListener("click", () => {
      if (history.length > 1) history.back();
      else location.href = "../index.html";
    });
  }

  /* ---------- Notify helpers ---------- */
  const hasNotify = typeof window.notify?.show === "function";
  const safeOk  = (m)=> hasNotify ? notify.show(m,"success",{title:"¡Listo!"}) : console.log(m);

  /* ---------- Boot ---------- */
  document.addEventListener("DOMContentLoaded", () => {
    sincronizarClavesEnStorage();   // (opcional) guarda claves faltantes
    renderTabla();
    initNav();
    $("#btnExcel")?.addEventListener("click", descargarExcel);
    $("#btnPDF")?.addEventListener("click", descargarPDF);
  });
})();
