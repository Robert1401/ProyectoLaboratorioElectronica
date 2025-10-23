// Navegación de botones y avisos (usa notify.js si está disponible)
(function () {
  const $ = (s) => document.querySelector(s);

  // rutas de destino
  const RUTAS = {
    inv: "reporte-inventario.html",
    mov: "reporte-movimiento.html",
    ase: "reporte-asesorias.html",
  };

  // Helpers de aviso (si existe notify.js lo usamos; si no, fallback con alert)
  const hasNotify = typeof window.notify?.show === "function";
  const ok  = (m) => hasNotify ? notify.show(m, "success", { title: "¡Listo!" }) : alert(m);

  // Botones "Generar"
  $("#btnInv")?.addEventListener("click", () => {
    ok("Abriendo reporte de inventario…");
    location.href = RUTAS.inv;
  });
  $("#btnMov")?.addEventListener("click", () => {
    ok("Abriendo reporte de material…");
    location.href = RUTAS.mov;
  });
  $("#btnAse")?.addEventListener("click", () => {
    ok("Abriendo reporte de asesorías…");
    location.href = RUTAS.ase;
  });

  // Botón volver (flecha)
  $("#btnBack")?.addEventListener("click", () => {
    if (history.length > 1) history.back();
    else location.href = "../index.html";
  });
})();
