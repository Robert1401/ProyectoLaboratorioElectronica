document.addEventListener("DOMContentLoaded", () => {
  const nombre = document.getElementById("nombre");
  const clave = document.getElementById("clave");
  const cantidad = document.getElementById("cantidad");
  const tabla = document.getElementById("tablaMateriales").querySelector("tbody");

  const btnGuardar = document.getElementById("btnGuardar");
  const btnActualizar = document.getElementById("btnActualizar");
  const btnEliminar = document.getElementById("btnEliminar");

  let filaSeleccionada = null;

  // === Guardar ===
  btnGuardar.addEventListener("click", () => {
    if (nombre.value && clave.value && cantidad.value) {
      const fila = tabla.insertRow();
      fila.insertCell(0).textContent = nombre.value;
      fila.insertCell(1).textContent = clave.value;
      fila.insertCell(2).textContent = cantidad.value;

      fila.addEventListener("click", () => {
        filaSeleccionada = fila;
        nombre.value = fila.cells[0].textContent;
        clave.value = fila.cells[1].textContent;
        cantidad.value = fila.cells[2].textContent;
      });

      nombre.value = clave.value = cantidad.value = "";
    } else {
      alert("Por favor completa todos los campos.");
    }
  });

  // === Actualizar ===
  btnActualizar.addEventListener("click", () => {
    if (filaSeleccionada) {
      filaSeleccionada.cells[0].textContent = nombre.value;
      filaSeleccionada.cells[1].textContent = clave.value;
      filaSeleccionada.cells[2].textContent = cantidad.value;
      nombre.value = clave.value = cantidad.value = "";
      filaSeleccionada = null;
    } else {
      alert("Selecciona una fila para actualizar.");
    }
  });

  // === Eliminar ===
  btnEliminar.addEventListener("click", () => {
    if (filaSeleccionada) {
      tabla.deleteRow(filaSeleccionada.rowIndex - 1);
      nombre.value = clave.value = cantidad.value = "";
      filaSeleccionada = null;
    } else {
      alert("Selecciona una fila para eliminar.");
    }
  });
});
