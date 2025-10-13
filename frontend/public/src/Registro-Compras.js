document.getElementById('agregar').addEventListener('click', () => {
  const material = document.getElementById('material').value;
  const cantidad = document.getElementById('cantidad').value;
  const fecha = document.getElementById('fecha').value;

  if (!material || !cantidad || !fecha) {
    alert('Por favor, complete todos los campos antes de agregar.');
    return;
  }

  const tabla = document.querySelector('#tablaMateriales tbody');
  const fila = document.createElement('tr');

  fila.innerHTML = `
    <td>${material}</td>
    <td>${cantidad}</td>
    <td>${fecha}</td>
  `;

  tabla.appendChild(fila);

  document.getElementById('material').value = '';
  document.getElementById('cantidad').value = 1;
});

document.getElementById('eliminar').addEventListener('click', () => {
  const tabla = document.querySelector('#tablaMateriales tbody');
  if (tabla.lastChild) tabla.removeChild(tabla.lastChild);
});

document.getElementById('guardar').addEventListener('click', () => {
  alert('Datos guardados correctamente (simulado)');
});

document.getElementById('actualizar').addEventListener('click', () => {
  alert('Funcionalidad de actualizar aún no implementada');
});
