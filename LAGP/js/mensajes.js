// --- Archivo: js/mensajes.js ---

function mostrarMensaje(mensaje, tipo = "error") {
  // Eliminar cualquier mensaje previo
  const existente = document.getElementById("toast");
  if (existente) existente.remove();

  // Crear el contenedor principal
  const toast = document.createElement("div");
  toast.id = "toast";

  // Crear la tarjeta del mensaje
  const card = document.createElement("div");
  card.className = `toast-card ${tipo}`;
  card.textContent = mensaje;

  toast.appendChild(card);
  document.body.appendChild(toast);

  // Cerrar con clic (animación + eliminación)
  card.addEventListener("click", () => {
    card.style.animation = "toastOut 0.25s ease forwards";
    toast.style.animation = "fadeOut 0.25s ease forwards";
    setTimeout(() => toast.remove(), 250);
  });
}

function mostrarConfirmacion(mensaje, func_aceptar = null, func_cancelar = null) {
  // Eliminar cualquier mensaje previo
  const existente = document.getElementById("fondo-Invisible");
  if (existente) existente.remove();

  // Crear el contenedor principal
  const fondo = document.createElement("div");
  fondo.id = "fondo-Invisible";

  // Crear la tarjeta del mensaje
  const cuerpo = document.createElement("div");
  cuerpo.className = `cuerpo-Mensaje`;
  const mensajeHTML = mensaje.replace(/\n/g, "<br>");
  cuerpo.innerHTML = mensajeHTML;

  //Contenedor de botones
  const botones = document.createElement("div")
  botones.id =  "botones";

  //Botones
  const aceptar = document.createElement("button");
  aceptar.className = "btn-Confirmacion";
  aceptar.textContent = "Aceptar";
  aceptar.onclick = function() {
    if (typeof func_aceptar === "function") func_aceptar();
    cuerpo.style.animation = "toastOut 0.25s ease forwards";
    fondo.style.animation = "fadeOut 0.25s ease forwards";
    setTimeout(() => fondo.remove(), 250);
  };
  const cancelar = document.createElement("button");
  cancelar.className = "btn-Confirmacion";
  cancelar.textContent = "Cancelar";
  cancelar.onclick = function() {
    if (typeof func_cancelar === "function") func_cancelar();
    cuerpo.style.animation = "toastOut 0.25s ease forwards";
    fondo.style.animation = "fadeOut 0.25s ease forwards";
    setTimeout(() => fondo.remove(), 250);
  };

  botones.appendChild(aceptar);
  botones.appendChild(cancelar);
  cuerpo.appendChild(botones);
  fondo.appendChild(cuerpo);
  document.body.appendChild(fondo);
}
