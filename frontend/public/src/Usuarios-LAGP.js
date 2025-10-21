
//Aparecer o desaparecer el combo
const ComboTipoRegistro = document.getElementById('ComboTipoRegistro');
const comboCarreras = document.getElementById('comboCarreras');
const spanCarreras = document.getElementById('spanCarreras');
//Validar
const numeroControl = document.getElementById('numeroControl');
const nombre = document.getElementById('nombre');
const paterno = document.getElementById('paterno');
const materno = document.getElementById('materno');
//Botón de contraeña
const CambiarClave = document.getElementById('CambiarClave');
//Selección de fila
let fila = null;

function alSeleccionar() {
  //Cuando es Auxiliar
  if(ComboTipoRegistro.value === '1'){
    //Cambia el número de carácteres
    numeroControl.value = '';
    numeroControl.setAttribute('maxlength', 4);
    CambiarClave.style.display = 'flex';
  }
  //Cuando es Alumno
  if (ComboTipoRegistro.value === '2') { 

    comboCarreras.style.display = 'block';
    spanCarreras.style.display = 'block';
    CambiarClave.style.display = 'flex';

    //Cambia el número de carácteres
    numeroControl.value = '';
    numeroControl.setAttribute('maxlength', 8);

    // Llama al PHP para obtener las carreras
      fetch("../../../backend/Usuarios-LAGP/LlenarComboCarreras.php")
        .then(res => res.json())
        .then(datos => {
          comboCarreras.innerHTML = '<option value="">Seleccione una carrera</option>';
          datos.forEach(c => {
            const option = document.createElement("option");
            option.value = c.id_Carrera;      // Es con lo que se identifica la opción
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
  //Cuando es Docente
  if(ComboTipoRegistro.value === '3'){
    //Cambia el número de carácteres
    numeroControl.value = '';
    numeroControl.setAttribute('maxlength', 4);
    CambiarClave.style.display = 'none';
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
                    
                    // Para modificar el elemento
                    tr.addEventListener("click", () => {
                        //Guardamos en una varibale el numero de control
                        fila = persona.id;
                        //Llenar los campos con la fila seleccionada, el event listener recuerda las variables cuando se guardaba
                        numeroControl.value = persona.id;
                        nombre.value = persona.nombre;
                        paterno.value = persona.apellidoPaterno;
                        materno.value = persona.apellidoMaterno;
                        buscarCarrera(persona.id);
                    });

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

function validar(){
  //Validar campos
  if(ComboTipoRegistro.value !== '2' && numeroControl.value.length === 4){
    console.log("Es docente o auxiliar con NC correcto");
  }else if(ComboTipoRegistro.value === '2' && numeroControl.value.length === 8){
    console.log("Es alumno con NC correcto");
    if(comboCarreras.value !== ''){
      console.log('Todo correcto');
    }else{
      alert("❌ Selecciona una opción de la lista");
      return false;
    }
  }else{
    alert("❌ Para Alumnos el Número de control es de 8 cifras, mientras que para Auxiliar y Docentes es de 4 cifras");
    return false;
  }

  if(nombre.checkValidity()){
      if(paterno.checkValidity()){
        if(materno.checkValidity()){
          console.log("Formulario común correcto");
          return true;
        }else{
          alert("❌ Valor inválido: " + materno.title);
          return false;
        }
      }else{
        alert("❌ Valor inválido: " + paterno.title);
        return false;
      }
    }else{
      alert("❌ Valor inválido: " + nombre.title);
      return false;
    }
}

function buscarCarrera(numeroControl) {
  // Llama al backend para obtener la carrera del alumno
  fetch("../../../backend/Usuarios-LAGP/BuscarCarrera.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "numeroControl=" + encodeURIComponent(numeroControl)
  })
  .then(response => response.json())
  .then(data => {
    if (data && data.id_Carrera) {
      comboCarreras.value = data.id_Carrera; // Selecciona la carrera en el combo
    } else {
      comboCarreras.value = '';
    }
  })
  .catch(error => {
    console.error("Error al buscar carrera:", error);
  });
}

function guardar(){
  //Validar datos antes de guardarlos
  if(!validar()){
    return;
  }

  //Ya validados guardarlos en variables
  const ncontrol = numeroControl.value.trim();
  const name = nombre.value.trim();
  const lastname = paterno.value.trim();
  const lastname2 = materno.value.trim();
  const career = comboCarreras.value.trim();

  fetch("../../../backend/Usuarios-LAGP/Guardar.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "id=" + encodeURIComponent(ComboTipoRegistro.value) +
            "&numeroControl=" + encodeURIComponent(ncontrol) + 
            "&nombre=" + encodeURIComponent(name) + 
            "&apellidoPaterno=" + encodeURIComponent(lastname) + 
            "&apellidoMaterno=" + encodeURIComponent(lastname2) + 
            "&carrera=" + encodeURIComponent(career)
  })
  .then(response => response.text())
  .then(data => {
      alert(data);
      //Limpiar campos
      numeroControl.value = '';
      nombre.value = '';
      paterno.value = '';
      materno.value = '';
      comboCarreras.value = '';

      desplegarTabla(); //Recarga la tabla automáticamente
  })
  .catch(error => console.error("Error:", error));  
}

function modificar() {
  if (!fila) {
    alert("⚠️ Selecciona primero una fila para modificar.");
    return;
  }

  // Validar los datos antes de enviarlos
  if (!validar()) {
    return;
  }

  const ncontrol = numeroControl.value.trim();
  const name = nombre.value.trim();
  const lastname = paterno.value.trim();
  const lastname2 = materno.value.trim();
  const career = comboCarreras.value.trim();
  const tipo = ComboTipoRegistro.value;

  fetch("../../../backend/Usuarios-LAGP/Modificar.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body:
      "id=" + encodeURIComponent(tipo) +
      "&numeroControl=" + encodeURIComponent(ncontrol) +
      "&nombre=" + encodeURIComponent(name) +
      "&apellidoPaterno=" + encodeURIComponent(lastname) +
      "&apellidoMaterno=" + encodeURIComponent(lastname2) +
      "&carrera=" + encodeURIComponent(career)
  })
    .then(response => response.text())
    .then(data => {
      alert(data);
      // Limpiar campos después de modificar
      numeroControl.value = "";
      nombre.value = "";
      paterno.value = "";
      materno.value = "";
      comboCarreras.value = "";
      fila = null;

      // Recargar la tabla
      desplegarTabla();
    })
    .catch(error => {
      console.error("Error al modificar:", error);
      alert("❌ Ocurrió un error al intentar modificar.");
    });
}

function eliminar() {
    if (!fila) {
        alert("⚠️ Selecciona primero un elemento de la tabla para eliminar");
        return;
    }

    // Confirmación de borrado lógico
    if (!confirm("¿Seguro que deseas eliminar (cambio de estado) este registro?")) return;

    fetch("../../../backend/Usuarios-LAGP/Eliminar.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "numeroControl=" + encodeURIComponent(numeroControl.value) +
              "&tipo=" + encodeURIComponent(ComboTipoRegistro.value)
    })
    .then(response => response.text())
    .then(data => {
        alert(data);

        // Limpiar campos
        numeroControl.value = '';
        nombre.value = '';
        paterno.value = '';
        materno.value = '';
        comboCarreras.value = '';

        fila = null;
        desplegarTabla(); // recarga la tabla
    })
    .catch(error => console.error("Error:", error));
}

//Se ejecuta al cargar el html
window.onload = desplegarTabla;