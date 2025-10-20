// Ruta relativa desde frontend/public/src/alumnos.js hacia backend/Alumnos-Aux.php
// Si tu estructura es la misma que usuarios.js, esta ruta funciona:
const API_URL_ALUMNOS = "../../../../backend/Alumnos-Aux.php";

async function fetchJson(url) {
  const res = await fetch(url);
  const txt = await res.text();
  console.log("GET", url, res.status, txt);
  let data;
  try { data = JSON.parse(txt); } catch { throw new Error("Respuesta no JSON"); }
  if (!res.ok) throw new Error(data.error || "Error de red");
  return data;
}

function renderFilas(rows) {
  const tbody = document.querySelector("#tablaAlumnos tbody");
  tbody.innerHTML = "";
  if (!Array.isArray(rows) || rows.length === 0) {
    const tr = document.createElement("tr");
    tr.innerHTML = `<td colspan="3" style="text-align:center; color:#7a0000;">Sin resultados</td>`;
    tbody.appendChild(tr);
    return;
  }

  rows.forEach(r => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${r.numeroControl ?? ""}</td>
      <td>${r.carrera ?? ""}</td>
      <td>${r.materia ?? ""}</td>
    `;
    tbody.appendChild(tr);
  });
}

async function cargarTabla(q = "") {
  try {
    const url = q ? `${API_URL_ALUMNOS}?q=${encodeURIComponent(q)}` : API_URL_ALUMNOS;
    const data = await fetchJson(url);
    renderFilas(data);
  } catch (e) {
    alert("❌ " + e.message);
  }
}

document.addEventListener("DOMContentLoaded", () => {
  // Carga inicial sin filtro
  cargarTabla();
  // Si agregas un input de búsqueda, aquí puedes engancharlo:
  // document.getElementById("buscar").addEventListener("keydown", e => { if(e.key==="Enter"){ cargarTabla(e.target.value.trim()) } });
});
