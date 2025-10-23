"use strict";

/* =========================================================
   CONFIG
========================================================= */
const API_URL = "http://localhost:8000/backend/login.php";

/* =========================================================
   TOAST (notificaciones)
========================================================= */
function showToast(message, type = "info", duration = 3000) {
  const host = document.getElementById("toast");
  host.innerHTML = `<div class="card ${type}" role="status">${message}</div>`;
  host.classList.add("show");

  const hide = () => {
    host.classList.remove("show");
    host.innerHTML = "";
  };

  const t = setTimeout(hide, duration);
  host.onclick = () => {
    clearTimeout(t);
    hide();
  };
}

/* =========================================================
   RESTRICCIÓN #numeroControl (solo números)
   - Bloquea letras al teclear
   - Purga texto no numérico al pegar o cambiar
   - Sin mostrar mensajes
========================================================= */
(() => {
  const nc = document.getElementById("numeroControl");
  if (!nc) return;

  nc.setAttribute("inputmode", "numeric");
  nc.setAttribute("pattern", "\\d*");
  nc.setAttribute("maxlength", "8");

  // Bloquea teclas no numéricas (permite controles y atajos)
  nc.addEventListener("keydown", (e) => {
    const allowed = ["Backspace", "Delete", "Tab", "ArrowLeft", "ArrowRight", "Home", "End"];
    if (allowed.includes(e.key) || e.ctrlKey || e.metaKey) return;
    if (/^\d$/.test(e.key)) return;
    e.preventDefault();
  });

  // Limpia pegado (solo deja dígitos)
  nc.addEventListener("paste", (e) => {
    const t = (e.clipboardData || window.clipboardData).getData("text") || "";
    if (/\D/.test(t)) {
      e.preventDefault();
      const digits = t.replace(/\D/g, "");
      if (!digits) return;

      const start = nc.selectionStart ?? nc.value.length;
      const end = nc.selectionEnd ?? nc.value.length;
      const max = nc.maxLength > 0 ? nc.maxLength : Infinity;

      const newVal = (nc.value.slice(0, start) + digits + nc.value.slice(end)).slice(0, max);
      const pos = Math.min(start + digits.length, newVal.length);

      nc.value = newVal;
      requestAnimationFrame(() => nc.setSelectionRange(pos, pos));
    }
  });

  // Fallback: purga cualquier carácter no numérico que se cuele
  nc.addEventListener("input", () => {
    const digitsOnly = nc.value.replace(/\D/g, "");
    if (digitsOnly !== nc.value) {
      nc.value = digitsOnly;
    }
  });
})();

/* =========================================================
   RESTRICCIÓN #password (máx. 10 caracteres)
========================================================= */
(() => {
  const pw = document.getElementById("password");
  if (!pw) return;

  pw.setAttribute("maxlength", "10");

  // Evita escribir más de 10 caracteres
  pw.addEventListener("keydown", (e) => {
    const allowed = ["Backspace", "Delete", "Tab", "ArrowLeft", "ArrowRight", "Home", "End"];
    if (allowed.includes(e.key) || e.ctrlKey || e.metaKey) return;
    if (pw.value.length >= 10) {
      e.preventDefault();
    }
  });

  // Evita pegar más de 10 caracteres
  pw.addEventListener("paste", (e) => {
    const paste = (e.clipboardData || window.clipboardData).getData("text") || "";
    const current = pw.value;
    const selectionLength = pw.selectionEnd - pw.selectionStart;
    const total = current.length - selectionLength + paste.length;
    if (total > 10) {
      e.preventDefault();
      pw.value = (current.slice(0, pw.selectionStart) + paste).slice(0, 10);
    }
  });
})();

/* =========================================================
   LOGIN — Manejo de envío de formulario
========================================================= */
document.getElementById("loginForm").addEventListener("submit", async (e) => {
  e.preventDefault();

  const numeroControl = (document.getElementById("numeroControl").value || "").trim();
  const password = (document.getElementById("password").value || "").trim();
  const btn = e.submitter || document.querySelector('#loginForm button[type="submit"]');

  // Campos requeridos
  if (!numeroControl || !password) {
    showToast("⚠️ Por favor, completa todos los campos.", "info", 3000);
    return;
  }

  // Debe ser numérico
  if (!/^\d+$/.test(numeroControl)) {
    showToast("🔎 El número de control debe ser numérico.", "info", 3200);
    return;
  }

  // Longitud válida (4 auxiliar, 8 alumno)
  const len = numeroControl.length;

  if (len < 4) {
    showToast("🔎 Faltan dígitos.", "info", 3200);
    return;
  }

  if (len >= 5 && len <= 7) {
    showToast("🔎 Debe tener 4 (auxiliar) o 8 (alumno) dígitos.", "info", 3500);
    return;
  }

  if (len > 8) {
    showToast("🔎 Te pasaste de dígitos.", "info", 3500);
    return;
  }

  // Validar longitud de contraseña
  if (password.length > 10) {
    showToast("🔐 La contraseña no puede superar los 10 caracteres.", "info", 3200);
    return;
  }

  // Petición al backend
  try {
    if (btn) btn.disabled = true;

    const res = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ numeroControl, password }),
    });

    if (!res.ok) {
      showToast(`❌ Error del servidor (${res.status}).`, "error", 3500);
      return;
    }

    const data = await res.json();

    showToast(
      data.message || "Operación realizada.",
      data.success ? "success" : "error",
      3000
    );

    if (data.success) {
      setTimeout(() => {
        const rol = (data.rol || "").toLowerCase();
        if (rol === "auxiliar") {
          window.location.href = "/frontend/public/Auxiliar/auxiliar.html";
        } else if (rol === "alumno") {
          window.location.href = "/frontend/public/Alumnos/alumnos-inicial.html";
        } else {
          showToast("ℹ️ Inicio de sesión correcto, pero el rol no es válido.", "info", 3200);
        }
      }, 600);
    }
  } catch (err) {
    console.error(err);
    showToast(
      "❌ No se pudo conectar con el servidor. Verifica tu conexión o que el backend esté activo.",
      "error",
      3500
    );
  } finally {
    if (btn) btn.disabled = false;
  }
});
