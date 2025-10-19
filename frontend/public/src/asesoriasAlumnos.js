document.addEventListener("DOMContentLoaded", () => {
  const contenedor = document.getElementById("contenedorAsesorias");

  fetch("asesorias.php")
    .then(res => res.json())
    .then(data => {
      contenedor.innerHTML = "";

      if (!data || data.length === 0) {
        contenedor.innerHTML = `
          <div class="mensaje-vacio">
            <p>No hay asesorías disponibles</p>
            <i class="fa-solid fa-book-open" style="font-size:80px; color:#bbb;"></i>
          </div>
        `;
        return;
      }

      data.forEach(a => {
        const card = document.createElement("div");
        card.classList.add("card");

        const sinCupo = a.participantes >= a.cupo_maximo;

        card.innerHTML = `
          <h3>${a.titulo}</h3>
          <p><strong>Asesor:</strong> ${a.asesor || "Por asignar"}</p>
          <p><strong>Descripción:</strong> ${a.motivo}</p>
          <p><strong>Fecha:</strong> ${a.fecha} ${a.hora}</p>
          <p><strong>Cupo:</strong> ${a.participantes}/${a.cupo_maximo}</p>
          <button class="btn-inscribirse" ${sinCupo ? "disabled" : ""} data-id="${a.id}">
            ${sinCupo ? "Sin cupo" : "Inscribirme"}
          </button>
        `;
        contenedor.appendChild(card);
      });

      document.querySelectorAll(".btn-inscribirse").forEach(btn => {
        btn.addEventListener("click", e => {
          const id = e.target.dataset.id;
          inscribirse(id, e.target);
        });
      });
    })
    .catch(() => {
      contenedor.innerHTML = `<p class="mensaje-vacio">Error al cargar asesorías.</p>`;
    });
});

function inscribirse(id, boton) {
  boton.disabled = true;
  boton.textContent = "Procesando...";

  const datos = new FormData();
  datos.append("id_asesoria", id);

  fetch("inscripcion.php", {
    method: "POST",
    body: datos
  })
  .then(res => res.json())
  .then(data => {
    alert(data.mensaje);
    if (data.estado !== "ok") {
      boton.disabled = false;
      boton.textContent = "Inscribirme";
    } else {
      boton.textContent = "Inscrito";
      boton.style.backgroundColor = "#4CAF50";
    }
  })
  .catch(() => {
    alert("Error al procesar la inscripción.");
    boton.disabled = false;
    boton.textContent = "Inscribirme";
  });
}
