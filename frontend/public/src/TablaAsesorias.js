// Cargar asesorías al cargar la página
document.addEventListener("DOMContentLoaded", () => {
  fetch("asesorias.php")
    .then(res => res.json())
    .then(data => {
      const tbody = document.querySelector("#tablaAsesorias tbody");
      tbody.innerHTML = "";

      data.forEach(a => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td>${a.titulo}</td>
          <td>${a.fecha} ${a.hora}</td>
          <td>${a.participantes}</td>
          <td>
            <button class="btn-ver" data-id="${a.id}">Editar</button>
          </td>
        `;
        tbody.appendChild(tr);
      });

      // Asignar evento a los botones de editar
      document.querySelectorAll(".btn-ver").forEach(btn => {
        btn.addEventListener("click", e => {
          const id = e.target.dataset.id;
          const asesoria = data.find(x => x.id === id);
          if (asesoria) {
            // Redirige con los datos como parámetros en la URL
            const params = new URLSearchParams(asesoria).toString();
            window.location.href = `crear_asesoria.html?${params}`;
          }
        });
      });
    })
    .catch(() => {
      alert("Error al cargar asesorías.");
    });
});
