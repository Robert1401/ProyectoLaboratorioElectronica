document.addEventListener("DOMContentLoaded", () => {
  const tabla = document.querySelector("#tablaAsesorias tbody");
  const btnAgregar = document.getElementById("agregar");

  btnAgregar.addEventListener("click", () => {
    const ncontrol = document.getElementById("ncontrol").value.trim();
    const fecha = document.getElementById("fecha").value;
    const estatus = document.getElementById("estatus").value;

    if (!ncontrol || !fecha) {
      alert("Por favor, completa todos los campos.");
      return;
    }

    const fila = document.createElement("tr");
    fila.innerHTML = `
      <td>${ncontrol}</td>
      <td>${fecha}</td>
      <td>${estatus.charAt(0).toUpperCase() + estatus.slice(1)}</td>
    `;

    tabla.appendChild(fila);

    // Limpiar campos
    document.getElementById("ncontrol").value = "";
    document.getElementById("fecha").value = "";
    document.getElementById("estatus").value = "pendiente";
  });
});
