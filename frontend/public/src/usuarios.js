const API_URL = "http://localhost:8000/backend/usuarios.php";

const el = {
  buscar: document.getElementById("buscar"),
  btnGuardar: document.getElementById("guardar"),
  btnActualizar: document.getElementById("actualizar"),
  btnEliminar: document.getElementById("eliminar"),
  nombre: document.getElementById("nombre"),
  apPaterno: document.getElementById("apPaterno"),
  apMaterno: document.getElementById("apMaterno"),
  numControl: document.getElementById("numControl"),
  rol: document.getElementById("rol"),
  carrera: document.getElementById("carrera"),
  usuario: document.getElementById("usuario"),
  contrasena: document.getElementById("contrasena"),
  searchIcon: document.querySelector(".search-box i"),
};

// ===============================
// 🚨 Mensaje de error flotante
// ===============================
const errorBox = document.createElement("div");
Object.assign(errorBox.style, {
  position: "fixed", top: "50%", left: "50%",
  transform: "translate(-50%, -50%)",
  backgroundColor: "rgba(255,0,0,0.9)", color: "#fff",
  padding: "20px 35px", borderRadius: "12px",
  fontSize: "1.2em", fontWeight: "bold", textAlign: "center",
  display: "none", zIndex: "9999"
});
document.body.appendChild(errorBox);

function showError(msg) {
  errorBox.textContent = msg;
  errorBox.style.display = "block";
  setTimeout(() => errorBox.style.display = "none", 2500);
}

function limpiarFormulario() {
  Object.values(el).forEach(x => {
    if (x && (x.tagName === "INPUT" || x.tagName === "SELECT")) x.value = "";
  });
}

// ===============================
// 🔹 Cargar roles (Alumno, Auxiliar) desde la BD
// ===============================
async function cargarRoles() {
  try {
    const res = await fetch(`${API_URL}?roles=1`);
    const data = await res.json();
    el.rol.innerHTML = `<option value="">Seleccione</option>`;
    data.forEach(r => {
      const opt = document.createElement("option");
      opt.value = r.rol;
      opt.textContent = r.rol;
      el.rol.appendChild(opt);
    });
  } catch (err) {
    console.error(err);
    showError("Error al cargar roles.");
  }
}

// ===============================
// 🔹 Cargar carreras desde la BD
// ===============================
async function cargarCarreras() {
  try {
    const res = await fetch(`${API_URL}?carreras=1`);
    const data = await res.json();
    el.carrera.innerHTML = `<option value="">Seleccione</option>`;
    data.forEach(c => {
      const opt = document.createElement("option");
      opt.value = c.id_Carrera;
      opt.textContent = c.nombre;
      el.carrera.appendChild(opt);
    });
  } catch (err) {
    console.error(err);
    showError("Error al cargar carreras.");
  }
}

// ===============================
// 🔍 Buscar usuario
// ===============================
async function buscarUsuario() {
  const num = el.buscar.value.trim();
  if (!num) return showError("Escribe un número de control o trabajador.");

  try {
    const res = await fetch(`${API_URL}?num=${encodeURIComponent(num)}`);
    const data = await res.json();
    if (data.error) {
      limpiarFormulario();
      return showError(data.error);
    }

    el.nombre.value = data.nombre || "";
    el.apPaterno.value = data.apellidoPaterno || "";
    el.apMaterno.value = data.apellidoMaterno || "";
    el.usuario.value = data.usuario || "";
    el.contrasena.value = data.clave ? "••••••" : ""; // Mostrar como puntos
    el.rol.value = data.rol || "";
    el.numControl.value = data.numero || "";
    el.carrera.value = data.id_Carrera || "";
  } catch (err) {
    console.error(err);
    showError("Error al conectar con el servidor.");
  }
}

// ===============================
// 💾 Guardar usuario
// ===============================
async function guardarUsuario() {
  const payload = {
    usuario: el.usuario.value.trim(),
    clave: el.contrasena.value.trim(),
    rol: el.rol.value,
    nombre: el.nombre.value.trim(),
    apellidoPaterno: el.apPaterno.value.trim(),
    apellidoMaterno: el.apMaterno.value.trim(),
    id_Carrera: el.carrera.value || null,
    numControl: el.rol.value === "Alumno" ? el.numControl.value.trim() : null,
    numTrabajador: el.rol.value === "Auxiliar" ? el.numControl.value.trim() : null,
  };

  if (!payload.usuario || !payload.clave || !payload.rol)
    return showError("Completa todos los campos.");

  try {
    const res = await fetch(API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    alert(data.mensaje || data.error);
    if (!data.error) limpiarFormulario();
  } catch (err) {
    console.error(err);
    showError("Error al guardar usuario.");
  }
}

// ===============================
// 🔄 Actualizar usuario
// ===============================
async function actualizarUsuario() {
  const payload = {
    usuario: el.usuario.value.trim(),
    clave: el.contrasena.value.trim(),
  };
  if (!payload.usuario || !payload.clave) return showError("Usuario y contraseña requeridos");

  try {
    const res = await fetch(API_URL, {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    alert(data.mensaje || data.error);
  } catch (err) {
    console.error(err);
    showError("Error al actualizar usuario");
  }
}

// ===============================
// ❌ Eliminar usuario
// ===============================
async function eliminarUsuario() {
  const usuario = el.usuario.value.trim();
  if (!usuario) return showError("Escribe un usuario");
  if (!confirm(`¿Eliminar usuario "${usuario}"?`)) return;

  try {
    const res = await fetch(API_URL, {
      method: "DELETE",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ usuario })
    });
    const data = await res.json();
    alert(data.mensaje || data.error);
    if (!data.error) limpiarFormulario();
  } catch (err) {
    console.error(err);
    showError("Error al eliminar usuario");
  }
}

// ===============================
// 🧩 Eventos
// ===============================
el.buscar.addEventListener("keyup", e => e.key === "Enter" && buscarUsuario());
el.searchIcon.addEventListener("click", buscarUsuario);
el.btnGuardar.addEventListener("click", guardarUsuario);
el.btnActualizar.addEventListener("click", actualizarUsuario);
el.btnEliminar.addEventListener("click", eliminarUsuario);

// ===============================
// 🔹 Inicialización
// ===============================
cargarRoles();
cargarCarreras();
