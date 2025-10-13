document.addEventListener("DOMContentLoaded", async () => {
  const nombreInput = document.getElementById("nombre");
  const carreraSelect = document.getElementById("carrera");
  const tablaBody = document.querySelector("#tablaMaterias tbody");
  const mensajes = document.getElementById("mensajes");
  let materias = [];
  let carreras = [];
  let filaSeleccionada = null;

  // Cargar carreras
  async function cargarCarreras() {
    try {
      const res = await fetch("/backend/materias.php?tipo=carreras");
      carreras = await res.json();
      carreraSelect.innerHTML = `<option value="">Seleccione una carrera</option>`;
      carreras.forEach(c => {
        const option = document.createElement("option");
        option.value = c.id_Carrera;
        option.textContent = c.nombre;
        carreraSelect.appendChild(option);
      });
      carreraSelect.disabled = false;
    } catch (err) {
      mensajes.textContent = "Error al cargar las carreras";
      console.error(err);
    }
  }

  // Cargar materias
  async function cargarMaterias() {
    try {
      const res = await fetch("/backend/materias.php");
      materias = await res.json();
      tablaBody.innerHTML = "";

      materias.forEach(m => {
        const fila = document.createElement("tr");
        fila.innerHTML = `
          <td>${m.materia}</td>
          <td>${m.carrera || ""}</td>
        `;
        fila.dataset.id = m.id_Materia;
        fila.dataset.idCarrera = m.id_Carrera; // Guardar id de carrera
        tablaBody.appendChild(fila);
      });
    } catch (err) {
      mensajes.textContent = "Error al cargar las materias";
      console.error(err);
    }
  }

  await cargarCarreras();
  await cargarMaterias();

  // Seleccionar fila
  tablaBody.addEventListener("click", e => {
    const fila = e.target.closest("tr");
    if (!fila) return;

    if (filaSeleccionada) filaSeleccionada.classList.remove("seleccionada");
    fila.classList.add("seleccionada");
    filaSeleccionada = fila;

    nombreInput.value = fila.children[0].textContent;
    carreraSelect.value = fila.dataset.idCarrera;
    carreraSelect.disabled = false; // permitir cambio de carrera
  });

  // Guardar nueva materia
  document.getElementById("guardar").addEventListener("click", async () => {
    const nombre = nombreInput.value.trim();
    const id_Carrera = carreraSelect.value;

    if (!nombre || !id_Carrera) {
      mensajes.textContent = "Seleccione una carrera y escriba el nombre de la materia";
      return;
    }

    try {
      const res = await fetch("/backend/materias.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ nombre, id_Carrera }),
      });
      const data = await res.json();
      mensajes.textContent = data.mensaje || data.error;
      await cargarMaterias();
      nombreInput.value = "";
      carreraSelect.value = "";
      filaSeleccionada = null;
    } catch (err) {
      mensajes.textContent = "Error al guardar la materia";
      console.error(err);
    }
  });

  // Actualizar materia (nombre y carrera)
  document.getElementById("actualizar").addEventListener("click", async () => {
    if (!filaSeleccionada) {
      mensajes.textContent = "Seleccione una materia para actualizar";
      return;
    }

    const id = filaSeleccionada.dataset.id;
    const nombre = nombreInput.value.trim();
    const id_Carrera = carreraSelect.value;

    if (!nombre || !id_Carrera) {
      mensajes.textContent = "Debe completar nombre y carrera para actualizar";
      return;
    }

    try {
      const res = await fetch("/backend/materias.php", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id_Materia: id, nombre, id_Carrera }),
      });
      const data = await res.json();
      if (data.error) {
        mensajes.textContent = data.error;
        return;
      }

      // Reflejar cambios visualmente
      filaSeleccionada.children[0].textContent = nombre;
      filaSeleccionada.children[1].textContent = carreraSelect.selectedOptions[0].textContent;
      filaSeleccionada.dataset.idCarrera = id_Carrera;

      mensajes.textContent = data.mensaje || "Materia actualizada correctamente";

      // Reset campos
      nombreInput.value = "";
      carreraSelect.value = "";
      filaSeleccionada.classList.remove("seleccionada");
      filaSeleccionada = null;

    } catch (err) {
      mensajes.textContent = "Error al actualizar la materia";
      console.error(err);
    }
  });

  // Eliminar materia
  document.getElementById("eliminar").addEventListener("click", async () => {
    if (!filaSeleccionada) {
      mensajes.textContent = "Seleccione una materia para eliminar";
      return;
    }

    if (!confirm("¿Eliminar esta materia?")) return;

    try {
      const id = filaSeleccionada.dataset.id;
      const res = await fetch("/backend/materias.php", {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id_Materia: id }),
      });
      const data = await res.json();
      mensajes.textContent = data.mensaje || data.error;
      filaSeleccionada.remove();
      nombreInput.value = "";
      carreraSelect.value = "";
      filaSeleccionada = null;
    } catch (err) {
      mensajes.textContent = "Error al eliminar la materia";
      console.error(err);
    }
  });
});
