
const ComboTipoRegistro = document.getElementById('ComboTipoRegistro');
const comboCarreras = document.getElementById('comboCarreras');
const spanCarreras = document.getElementById('spanCarreras');

function alSeleccionar() {
  if (ComboTipoRegistro.value === '2') { //2 es el value: del comboBox de Alumno
    comboCarreras.style.display = 'block';
    spanCarreras.style.display = 'block';

    // Llama al PHP para obtener las ciudades
      fetch("../../../backend/Usuarios-LAGP/LlenarComboCarreras.php")
        .then(res => res.json())
        .then(datos => {
          comboCarreras.innerHTML = '<option value="">Seleccione una carrera</option>';
          datos.forEach(c => {
            const option = document.createElement("option");
            option.value = c.nombre;      // Es con lo que se identifica la opción
            option.textContent = c.nombre; // Texto visible
            comboCarreras.appendChild(option);
        });
        })
        .catch(err => {
        console.error("Error:", err);
        comboCarreras.innerHTML = '<option>Error al cargar</option>';
    });
  } else {
    comboCarreras.style.display = 'none';
    spanCarreras.style.display = 'none';
  }
  //Llenar la tabla
  desplegarTabla();
}

function desplegarTabla(){
  fetch('../../../backend/Usuarios-LAGP/LlenarTabla.php', {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "id=" + encodeURIComponent(ComboTipoRegistro.value)
    })
        .then(response => response.json())
        .then(data => {
            const tbody = document.querySelector('#tablaDinamica tbody');
            tbody.innerHTML = ''; // Limpiar tabla

            if(data.length > 0){
                data.forEach(persona => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${persona.id}</td>
                        <td>${persona.nombre}</td>
                        <td>${persona.apellidoPaterno}</td>
                        <td>${persona.apellidoMaterno}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4">No hay datos</td></tr>';
            }
        })
        .catch(error => {
            console.error('Servicio MySQL Apagado:', error);
            const tbody = document.querySelector('#tablaDinamica tbody');
            tbody.innerHTML = '<tr><td colspan="4">⚠️ Error al cargar los datos</td></tr>';
        });
}

function validar() {
  const input = document.getElementById("numeroControl");
  
  if (!input.checkValidity()) {
    alert("❌ Valor inválido: " + input.title);
    return
  }
}

window.onload = desplegarTabla;