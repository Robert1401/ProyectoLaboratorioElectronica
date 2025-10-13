// Botones de navegación
document.getElementById("btn-volver").addEventListener("click", () => {
  window.history.back();
});

document.getElementById("btn-inicio").addEventListener("click", () => {
  window.location.href = "index.html"; // Ajusta a tu página principal
});
