function showToast(message, type = "info", duration = 3000) {
  const host = document.getElementById("toast");
  host.innerHTML = `<div class="card ${type}" role="status">${message}</div>`;
  host.classList.add("show");
  const hide = () => { host.classList.remove("show"); host.innerHTML = ""; };
  const t = setTimeout(hide, duration);
  host.onclick = () => { clearTimeout(t); hide(); };
}

document.getElementById("loginForm").addEventListener("submit", async (e) => {
  e.preventDefault();

  const numeroControl = (document.getElementById("numeroControl").value || "").trim();
  const password      = (document.getElementById("password").value || "").trim();
  const btn = e.submitter || document.querySelector('#loginForm button[type="submit"]');

  if (!numeroControl || !password) {
    showToast("⚠️ Por favor, completa todos los campos.", "info", 3000);
    return;
  }

  // Validación básica: 8 dígitos (ajústalo si tu formato real es distinto)
 // Debe ser numérico
if (!/^\d+$/.test(numeroControl)) {
  showToast("🔎 El número de control debe ser numérico.", "info", 3200);
  return;
}

const len = numeroControl.length;

// Caso: muy pocos dígitos
if (len < 4) {
  showToast("🔎 Faltan dígitos.", "info", 3200);
  return;
}

// Caso: posibles auxiliares (4) o alumnos (8)
// Si es 5, 6 o 7 → no coincide con ningún rol válido
if (len >= 5 && len <= 7) {
  showToast("🔎 Debe tener 4 (auxiliar) o 8 (alumno) dígitos.", "info", 3500);
  return;
}

// Caso: demasiados dígitos
if (len > 8) {
  showToast("🔎 Te pasaste de dígitos.", "info", 3500);
  return;
}



  try {
    btn && (btn.disabled = true);

    const res = await fetch("http://localhost:8000/backend/login.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ numeroControl, password }),
    });

    if (!res.ok) {
      showToast(`❌ Error del servidor (${res.status}).`, "error", 3500);
      return;
    }

    const data = await res.json();

    showToast(data.message || "Operación realizada.", data.success ? "success" : "error", 3000);

    if (data.success) {
      setTimeout(() => {
        const rol = (data.rol || "").toLowerCase();
        if (rol === "auxiliar") {
          window.location.href = "/frontend/public/Auxiliar/auxiliar.html";
        } else if (rol === "alumno") {
          window.location.href = "/frontend/public/Alumnos/alumnos.html";
        } else {
          showToast("ℹ️ Inicio de sesión correcto, pero el rol no es válido.", "info", 3200);
        }
      }, 600);
    }
  } catch (err) {
    console.error(err);
    showToast("❌ No se pudo conectar con el servidor. Verifica tu conexión o que el backend esté activo.", "error", 3500);
  } finally {
    btn && (btn.disabled = false);
  }
});
