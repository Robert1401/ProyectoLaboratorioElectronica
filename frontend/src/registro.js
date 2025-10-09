document.addEventListener("DOMContentLoaded", () => {
  const carreraSelect = document.getElementById("carrera");
  const materiaSelect = document.getElementById("materia");
  const mensaje = document.getElementById("mensaje");

  const materiasPorCarrera = {
    1: [
      { valor: "CE101", nombre: "Circuitos Eléctricos I" },
      { valor: "CE102", nombre: "Circuitos Eléctricos II" }
    ],
    2: [
      { valor: "FD101", nombre: "Fundamentos Eléctricos" },
      { valor: "AD102", nombre: "Aplicaciones Digitales" }
    ]
  };

  carreraSelect.addEventListener("change", () => {
    const carreraID = carreraSelect.value;
    materiaSelect.innerHTML = '<option value="">Selecciona la Materia</option>';
    if (materiasPorCarrera[carreraID]) {
      materiasPorCarrera[carreraID].forEach(m => {
        const option = document.createElement("option");
        option.value = m.valor;
        option.textContent = m.nombre;
        materiaSelect.appendChild(option);
      });
    }
  });

  document.getElementById("registroForm").addEventListener("submit", (e) => {
    e.preventDefault();

    const clave = document.getElementById("clave").value.trim();
    if (!/^[A-Za-z0-9]{1,10}$/.test(clave)) {
      mensaje.textContent = "⚠️ La contraseña debe tener máximo 10 caracteres alfanuméricos.";
      mensaje.className = "mensaje error";
      return;
    }

    const datos = {
      numeroControl: document.getElementById("numeroControl").value.trim(),
      nombre: document.getElementById("nombre").value.trim(),
      apellidoPaterno: document.getElementById("apellidoPaterno").value.trim(),
      apellidoMaterno: document.getElementById("apellidoMaterno").value.trim(),
      carrera: document.getElementById("carrera").value,
      materia: document.getElementById("materia").value,
      clave: clave
    };

    fetch("registro.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(datos)
    })
    .then(resp => resp.json())
    .then(data => {
      mensaje.textContent = data.message;
      mensaje.className = data.success ? "mensaje exito" : "mensaje error";
      if (data.success) setTimeout(() => window.location.href = "index.html", 1500);
    })
    .catch(() => {
      mensaje.textContent = "❌ Error al conectar con el servidor.";
      mensaje.className = "mensaje error";
    });
  });
});
