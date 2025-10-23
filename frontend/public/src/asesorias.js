/* ============================================
   Gestión de Asesorías (localStorage)
   - Semilla 2025 con 1 Finalizada, 1 En curso, 2 Pendientes
   - Estatus automático por fecha/hora
   - Restricción: NO fechas pasadas en el formulario
============================================ */

/* --- Usa la fecha “hoy” forzada para mantener los ejemplos estables --- */
const FORCED_TODAY = "2025-10-21"; // pon null para usar el reloj real

/* ---------- Helpers de fechas ---------- */
const pad2 = n => String(n).padStart(2, "0");
const toISO = d => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
const addDays = (baseISO, n) => {
  const d = new Date(baseISO + "T00:00:00");
  d.setDate(d.getDate() + n);
  return toISO(d);
};
const getNow = () => (FORCED_TODAY ? new Date(`${FORCED_TODAY}T12:00:00`) : new Date());
const todayISO = () => (FORCED_TODAY ? FORCED_TODAY : toISO(new Date()));

/* minutos desde 00:00 */
function parseHHMM(str) {
  const [h, m] = (str || "00:00").split(":").map(x => parseInt(x, 10));
  if (Number.isNaN(h) || Number.isNaN(m)) return 0;
  return h * 60 + m;
}
function nowMinutes(now = getNow()) { return now.getHours() * 60 + now.getMinutes(); }

/* Estatus por fecha/hora */
function calcStatus({ fecha, hora }, now = getNow()) {
  try {
    const hoy = todayISO();
    const [hIni, hFin] = (hora || "00:00 - 23:59").split("-").map(s => s.trim());
    const minIni = parseHHMM(hIni);
    const minFin = parseHHMM(hFin);
    const minNow = nowMinutes(now);

    if (fecha < hoy) return "Finalizada";
    if (fecha > hoy) return "Pendiente";
    // fecha === hoy
    if (minNow < minIni) return "Pendiente";
    if (minNow > minFin) return "Finalizada";
    return "En curso";
  } catch { return "Pendiente"; }
}

/* ---------- Semilla por defecto (2025) ---------- */
function initializeDefaultSessions() {
  const existing = localStorage.getItem("asesorias");
  if (existing) return;

  const base = todayISO();               // 2025-10-21
  const now = getNow();
  const hour = now.getHours();
  const minute = now.getMinutes();

  // ventana “en curso” = una hora antes a una hora después de ahora
  const startHour = Math.max(0, hour - 1);
  const endHour   = Math.min(23, hour + 1);
  const horaCurso = `${pad2(startHour)}:${pad2(minute)} - ${pad2(endHour)}:${pad2(minute)}`;

  const seed = [
    // 1) FINALIZADA (19-oct-2025)
    {
      id: "1",
      titulo: "Bases de Datos Relacionales",
      auxiliar: "Ing. Ana Martínez",
      descripcion: "Diseño y normalización de bases; consultas SQL avanzadas.",
      fecha: "2025-10-19",
      hora: "16:00 - 18:00",
      cupoActual: 12,
      cupoTotal: 12
    },
    // 2) EN CURSO (hoy)
    {
      id: "2",
      titulo: "Análisis de Circuitos con SPICE",
      auxiliar: "Ing. María González",
      descripcion: "Simulación de circuitos AC/DC con SPICE.",
      fecha: base,
      hora: horaCurso,
      cupoActual: 5,
      cupoTotal: 8
    },
    // 3) PENDIENTE (hoy + 2)
    {
      id: "3",
      titulo: "Programación en Python Avanzado",
      auxiliar: "Ing. Carlos Ramírez",
      descripcion: "Estructuras de datos, algoritmos y POO.",
      fecha: addDays(base, 2),
      hora: "10:00 - 12:00",
      cupoActual: 3,
      cupoTotal: 10
    },
    // 4) PENDIENTE (hoy + 7)
    {
      id: "4",
      titulo: "Desarrollo Web con React",
      auxiliar: "Ing. Luis Hernández",
      descripcion: "Componentes, hooks, estado y props.",
      fecha: addDays(base, 7),
      hora: "15:00 - 17:00",
      cupoActual: 7,
      cupoTotal: 15
    }
  ].map(s => ({ ...s, status: calcStatus(s) }));

  localStorage.setItem("asesorias", JSON.stringify(seed));
}

/* ---------- UI Helpers ---------- */
function setDateMin(selector = "#fecha") {
  const inp = document.querySelector(selector);
  if (inp) {
    inp.min = todayISO();     // bloquea días pasados en el datepicker
    // si tenía una fecha pasada, la limpia
    if (inp.value && inp.value < inp.min) inp.value = inp.min;
  }
}

/* ---------- Render ---------- */
function loadSessions() {
  initializeDefaultSessions();
  const sessions = JSON.parse(localStorage.getItem("asesorias") || "[]");
  const now = getNow();

  // recalcular status en cada render
  const updated = sessions.map(s => ({ ...s, status: calcStatus(s, now) }));
  localStorage.setItem("asesorias", JSON.stringify(updated));

  const container = document.getElementById("asesoriasContainer");
  container.innerHTML = updated.map(session => {
    const statusClass =
      session.status === "En curso" ? "status-en-curso" :
      session.status === "Finalizada" ? "status-finalizada" : "status-pendiente";

    return `
      <div class="asesoria-card" data-id="${session.id}">
        <h2 class="card-title">${session.titulo}</h2>
        <div class="card-info">
          <p class="info-item"><span class="icon">👨‍🏫</span><strong>Auxiliar:</strong> ${session.auxiliar}</p>
          <p class="info-item"><span class="icon">📝</span><strong>Descripción:</strong> ${session.descripcion}</p>
          <p class="info-item"><span class="icon">📅</span><strong>Fecha:</strong> ${session.fecha}</p>
          <p class="info-item"><span class="icon">🕐</span><strong>Hora:</strong> ${session.hora}</p>
          <p class="info-item"><span class="icon">👥</span><strong>Cupo:</strong> ${session.cupoActual}/${session.cupoTotal}</p>
          <div class="status-wrapper">
            <span class="status-badge ${statusClass}">${session.status}</span>
          </div>
        </div>
        <div class="card-actions">
          <button class="btn-edit" onclick="openEditModal('${session.id}')">✏️ Editar</button>
          <button class="btn-view" onclick="openStudentsModal('${session.titulo}', '${session.id}')">👁️ Ver inscritos</button>
        </div>
      </div>`;
  }).join("");
}

/* ---------- Modales ---------- */
function openModal() {
  document.getElementById("modalTitle").textContent = "Crear Nueva Asesoría";
  document.getElementById("asesoriaId").value = "";
  document.getElementById("titulo").value = "";
  document.getElementById("auxiliar").value = "";
  document.getElementById("descripcion").value = "";
  document.getElementById("fecha").value = "";
  document.getElementById("hora").value = "";
  document.getElementById("cupo").value = "";
  setDateMin("#fecha"); // fecha mínima = hoy
  document.getElementById("modal").style.display = "flex";
}

function openEditModal(asesoriaId) {
  const sessions = JSON.parse(localStorage.getItem("asesorias") || "[]");
  const a = sessions.find(s => s.id === asesoriaId);
  if (!a) return;

  document.getElementById("modalTitle").textContent = "Editar Asesoría";
  document.getElementById("asesoriaId").value = a.id;
  document.getElementById("titulo").value = a.titulo;
  document.getElementById("auxiliar").value = a.auxiliar;
  document.getElementById("descripcion").value = a.descripcion;
  document.getElementById("fecha").value = a.fecha;
  document.getElementById("hora").value = a.hora;
  document.getElementById("cupo").value = a.cupoTotal;
  setDateMin("#fecha"); // aplica también en edición
  document.getElementById("modal").style.display = "flex";
}

function closeModal() { document.getElementById("modal").style.display = "none"; }

/* ---------- Guardar (crear/editar) ---------- */
function saveAsesoria(event) {
  event.preventDefault();

  const id   = document.getElementById("asesoriaId").value;
  const titulo = document.getElementById("titulo").value.trim();
  const auxiliar = document.getElementById("auxiliar").value.trim();
  const descripcion = document.getElementById("descripcion").value.trim();
  const fecha = document.getElementById("fecha").value;
  const hora  = document.getElementById("hora").value.trim();
  const cupoTotal = parseInt(document.getElementById("cupo").value, 10);

  // bloquear fechas pasadas
  const min = todayISO();
  if (!fecha || fecha < min) {
    alert("La fecha no puede ser anterior a hoy.");
    setDateMin("#fecha");
    return;
  }

  let sessions = JSON.parse(localStorage.getItem("asesorias") || "[]");
  const base = { titulo, auxiliar, descripcion, fecha, hora, cupoTotal };
  const status = calcStatus(base);

  if (id) {
    const idx = sessions.findIndex(s => s.id === id);
    if (idx !== -1) sessions[idx] = { ...sessions[idx], ...base, status };
  } else {
    sessions.push({
      id: Date.now().toString(),
      ...base,
      cupoActual: 0,
      status
    });
  }

  localStorage.setItem("asesorias", JSON.stringify(sessions));
  alert("Asesoría guardada exitosamente");
  closeModal();
  loadSessions();
}

/* ---------- Modal alumnos (mock) ---------- */
function openStudentsModal(asesoriaTitle, asesoriaId) {
  const modal = document.getElementById("studentsModal");
  const title = document.getElementById("studentsModalTitle");
  const list = document.getElementById("studentsList");

  title.textContent = `Alumnos Inscritos - ${asesoriaTitle}`;

  const mockStudents = [
    { numeroControl: "20400751", nombre: "Juan Carlos Pérez García" },
    { numeroControl: "20400752", nombre: "María Fernanda López Martínez" },
    { numeroControl: "20400753", nombre: "Luis Alberto Rodríguez Sánchez" }
  ];

  list.innerHTML = mockStudents.map((s, i) => `
    <div class="student-item">
      <span class="student-number">${i + 1}</span>
      <div class="student-info">
        <p class="student-control"><strong>N. Control:</strong> ${s.numeroControl}</p>
        <p class="student-name"><strong>Nombre:</strong> ${s.nombre}</p>
      </div>
    </div>`).join("");

  modal.style.display = "flex";
}
function closeStudentsModal(){ document.getElementById("studentsModal").style.display = "none"; }
window.onclick = function(e){
  const m1 = document.getElementById("modal");
  const m2 = document.getElementById("studentsModal");
  if (e.target === m1) closeModal();
  if (e.target === m2) closeStudentsModal();
};

/* ---------- Boot ---------- */
document.addEventListener("DOMContentLoaded", () => {
  loadSessions();
  // por si el input de fecha existe en el DOM desde inicio (algunos navegadores), fija min:
  setDateMin("#fecha");
});
