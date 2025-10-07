document.getElementById("registroForm").addEventListener("submit", function(event) {
  event.preventDefault();

  const numeroControl = document.getElementById("numeroControl").value.trim();
  const nombre = document.getElementById("nombre").value.trim();
  const apellidoPaterno = document.getElementById("apellidoPaterno").value.trim();
  const apellidoMaterno = document.getElementById("apellidoMaterno").value.trim();
  const materia = document.getElementById("materia").value.trim();
  const carrera = document.getElementById("carrera").value.trim();
  const clave = document.getElementById("clave").value.trim();

  if (!numeroControl || !nombre || !apellidoPaterno || !apellidoMaterno || !materia || !carrera || !clave) {
    alert("⚠️ Por favor, complete todos los campos.");
    return;
  }

  // Enviar los datos al servidor
  fetch('registro.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      numeroControl,
      nombre,
      apellidoPaterno,
      apellidoMaterno,
      materia,
      carrera,
      clave
    })
  })
  .then(response => response.json())
  .then(data => {
    alert(data.message);
    if (data.success) {
      // Redirigir al login
      window.location.href = 'index.html';
    }
  })
  .catch(error => {
    console.error('❌ Error al registrar:', error);
    alert("Ocurrió un error al registrar. Intente nuevamente.");
  });
});

const materiasPorCarrera = {
  IE: [
    { valor: "CEI", nombre: "Circuitos Eléctricos I" },
    { valor: "CEII", nombre: "Circuitos Eléctricos II" },
    { valor: "EA", nombre: "Electrónica Analógica" },
    { valor: "EM", nombre: "Electromagnetismo" },
    { valor: "ME", nombre: "Medidas / Mediciones Eléctricas" }
  ],
  IM: [
    { valor: "INST", nombre: "Instrumentación" },
    { valor: "ACE", nombre: "Análisis de Circuitos Eléctricos" }
  ],
  ISC: [
    { valor: "FEAD", nombre: "Fundamentos Eléctricos y Aplicaciones Digitales" }
  ],
  IMC: [
    { valor: "SE", nombre: "Sistemas Electrónicos" },
    { valor: "MF", nombre: "Mecánica de Fluidos / Termodinámica" }
  ],
  IMAT: [
    { valor: "EMO", nombre: "Electricidad, magnetismo y óptica" },
    { valor: "TA", nombre: "Técnicas de análisis" },
    { valor: "FES", nombre: "Física del estado sólido" }
  ]
};

const carreraSelect = document.getElementById("carrera");
const materiaSelect = document.getElementById("materia");

carreraSelect.addEventListener("change", function() {
  const carrera = this.value;
  materiaSelect.innerHTML = '<option value="">Selecciona la Materia</option>';

  if (materiasPorCarrera[carrera]) {
    materiasPorCarrera[carrera].forEach(m => {
      const option = document.createElement("option");
      option.value = m.valor;
      option.textContent = m.nombre;
      materiaSelect.appendChild(option);
    });
  }
});
