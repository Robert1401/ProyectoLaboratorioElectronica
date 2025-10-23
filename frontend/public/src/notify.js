/* =========================================================
   Notify — Avisos centrados (overlay + animación)
   Uso:
     notify.show("Guardado con éxito", "success", {title:"¡Listo!"});
     notify.show("Algo salió mal", "error");
     notify.confirm("¿Eliminar registro?", { okText:"Eliminar" })
       .then(ok => { if(ok){ ... } });
   Tipos: "success" | "error" | "info" | "warning"
========================================================= */
(function () {
  const ICONS = {
    success:'<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12 2a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2zm-1 14l-4-4 1.414-1.414L11 12.172l5.586-5.586L18 8l-7 8z"/></svg>',
    error:'<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12 2a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2zm-1 5h2v7h-2V7zm0 9h2v2h-2v-2z"/></svg>',
    info:'<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12 2a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2zm-1 5h2v2h-2V7zm0 4h2v6h-2v-6z"/></svg>',
    warning:'<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>'
  };

  function ensureDOM() {
    if (document.getElementById("nv-layer")) return;
    const css = `
#nv-layer{position:fixed;inset:0;z-index:99999;display:none;place-items:center;}
#nv-layer.nv-open{display:grid;}
.nv-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);opacity:0;transition:opacity .18s;}
.nv-card{position:relative;max-width:540px;width:min(92%,540px);background:#fff;border-radius:16px;padding:18px 16px 14px;
  box-shadow:0 22px 60px rgba(0,0,0,.35);transform:translateY(16px) scale(.98);opacity:0;transition:transform .22s,opacity .22s;}
.nv-show .nv-backdrop{opacity:1;}
.nv-show .nv-card{transform:translateY(0) scale(1);opacity:1;}
.nv-head{display:flex;gap:10px;align-items:center;margin-bottom:6px;}
.nv-icon{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;color:#fff;flex-shrink:0;}
.nv-title{font-weight:800;color:#1f2937;font-size:18px;margin:0;}
.nv-msg{color:#374151;margin:6px 2px 10px;line-height:1.45;}
.nv-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:10px;}
.nv-btn{border:0;border-radius:12px;padding:10px 16px;font-weight:700;cursor:pointer;transition:transform .12s,box-shadow .12s;}
.nv-btn:active{transform:translateY(1px) scale(.99);}
.nv-ok{background:#065f46;color:#fff;}
.nv-cancel{background:#e5e7eb;color:#111827;}
/* Temas */
.nv-success .nv-icon{background:#10b981;}
.nv-error   .nv-icon{background:#dc2626;}
.nv-info    .nv-icon{background:#111827;}
.nv-warning .nv-icon{background:#f59e0b;}
/* Badge host */
.nv-host{position:absolute;right:10px;top:10px;background:#7a0000;color:#fff;padding:4px 8px;border-radius:999px;
  font-size:11px;font-weight:700;letter-spacing:.2px;box-shadow:0 8px 18px rgba(122,0,0,.35);}
`;
    const style = document.createElement("style");
    style.textContent = css;

    const layer = document.createElement("div");
    layer.id = "nv-layer";
    layer.innerHTML = `
      <div class="nv-backdrop"></div>
      <div class="nv-card nv-info">
        <div class="nv-host">${location.host || "localhost"}</div>
        <div class="nv-head">
          <div class="nv-icon">${ICONS.info}</div>
          <h3 class="nv-title">Aviso</h3>
        </div>
        <div class="nv-msg">Mensaje…</div>
        <div class="nv-actions"></div>
      </div>
    `;

    document.head.appendChild(style);
    document.body.appendChild(layer);

    layer.addEventListener("click", (e) => {
      if (e.target.classList.contains("nv-backdrop")) hide();
    });
    document.addEventListener("keydown", (e) => { if (e.key === "Escape") hide(); });
  }

  function setType(type) {
    const card = document.querySelector("#nv-layer .nv-card");
    card.className = `nv-card nv-${type}`;
    const icon = document.querySelector("#nv-layer .nv-icon");
    icon.innerHTML = ICONS[type] || ICONS.info;
    const title = document.querySelector("#nv-layer .nv-title");
    title.textContent = (type==="success"?"¡Listo!":type==="error"?"Error":type==="warning"?"Atención":"Aviso");
  }

  function show(text="", type="info", { title, timeout=1600 } = {}) {
    ensureDOM();
    const layer = document.getElementById("nv-layer");
    const msg   = layer.querySelector(".nv-msg");
    const actions = layer.querySelector(".nv-actions");

    setType(type);
    if (title) layer.querySelector(".nv-title").textContent = title;
    msg.textContent = text;
    actions.innerHTML = `<button class="nv-btn nv-ok">Aceptar</button>`;

    layer.classList.add("nv-open");
    requestAnimationFrame(() => layer.classList.add("nv-show"));
    actions.querySelector(".nv-ok").onclick = hide;
    if (timeout) setTimeout(hide, timeout);
  }

  function confirm(text, { title="Confirmar", okText="Aceptar", cancelText="Cancelar" } = {}) {
    ensureDOM();
    return new Promise((resolve) => {
      const layer = document.getElementById("nv-layer");
      const msg   = layer.querySelector(".nv-msg");
      const actions = layer.querySelector(".nv-actions");

      setType("warning");
      layer.querySelector(".nv-title").textContent = title;
      msg.textContent = text;
      actions.innerHTML = `
        <button class="nv-btn nv-cancel">${cancelText}</button>
        <button class="nv-btn nv-ok">${okText}</button>
      `;

      layer.classList.add("nv-open");
      requestAnimationFrame(() => layer.classList.add("nv-show"));
      actions.querySelector(".nv-cancel").onclick = () => { hide(); resolve(false); };
      actions.querySelector(".nv-ok").onclick     = () => { hide(); resolve(true);  };
    });
  }

  function hide() {
    const layer = document.getElementById("nv-layer");
    if (!layer) return;
    layer.classList.remove("nv-show");
    setTimeout(() => layer.classList.remove("nv-open"), 180);
  }

  window.notify = { show, confirm, hide };
})();
