//Botones
const btnGuardar = document.getElementById("Guardar");
    btnGuardar.addEventListener("click", guardarMateria);
const btnModificar = document.getElementById("Modificar");
    btnModificar.addEventListener("click", modificarMateria);
const btnEliminar = document.getElementById("Eliminar");
    btnEliminar.addEventListener("click", eliminarMateria);
const btnCancelar = document.getElementById('Cancelar');
    btnCancelar.addEventListener("click", detenerProceso);
    btnCancelar.style.display = 'none';
//Input
const nombreMateria = document.getElementById("nombreMateria");
    nombreMateria.addEventListener("input", validar);
//Variables
let nombreOriginal = null;
let fila = null;
let seleccionado = false;

function validar(){
  //Desactivar botón Guardar
  btnGuardar.disabled = true;
  btnModificar.disabled = true;
  if(!nombreMateria.checkValidity()){ return false;} 
  //Para reusar el método con la función modificar
  if(seleccionado){
    btnModificar.disabled = false;
    btnEliminar.disabled = true;
    return true;
  }
  btnGuardar.disabled = false;
}

function detenerProceso(){
  //Regresar al modo normal
    btnModificar.disabled = true;
    seleccionado = false;
    btnCancelar.style.display = 'none';
    btnEliminar.disabled = true;
    fila = null;
    nombreMateria.value = '';
}

function desactivarBotones(){
  btnGuardar.disabled = true;
  btnEliminar.disabled = true;
  btnModificar.disabled = true;
}

function hayProgreso(){
  if(nombreMateria.value !== ''){
    return true;
  }else{
    return false;
  }
}

function salir(){
  if(hayProgreso()){
    mostrarConfirmacion('Tienes datos sin guardar en el formulario. Si sales ahora perderás datos. ¿Deseas salir igualmente?', 
                        function() { location.href = "../auxiliar.html"; }
    );
  }else{
    location.href = "../auxiliar.html";
  }
}

function cargarMaterias() {
    fetch('../../php/Materias-LAGP/TablaMaterias.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.querySelector('#tablaDinamica tbody');
            tbody.innerHTML = ''; // Limpiar tabla

            if(data.length > 0){
                data.forEach(materia => {
                    const fila = document.createElement('tr');
                    fila.innerHTML = `<td>${materia.nombre}</td>`;

                    // Para modificar el elemento
                    fila.addEventListener("click", () => {
                        nombreOriginal = materia.nombre; // Guardamos el nombre actual
                        nombreMateria.value = materia.nombre; // cargamos input
                        //Modificar o Eliminar
                        seleccionado = true;
                        btnModificar.disabled = true;
                        btnEliminar.disabled = false;
                        btnCancelar.style.display = 'block';
                    });
                    
                    tbody.appendChild(fila);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="1">No hay datos</td></tr>';
            }
        })
        .catch(error => {
            console.error('Servicio MySQL Apagado:', error);
            const tbody = document.querySelector('#tablaDinamica tbody');
            tbody.innerHTML = '<tr><td colspan="1">⚠️ Error al cargar los datos</td></tr>';
        });
}

// --- Guardar nueva materia ---
function guardarMateria() {
    const valor = nombreMateria.value.trim(); //Guarda el contenido en una variable y quita espacios al principio y final

    fetch("../../php/Materias-LAGP/GuardarMateria.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "nombre=" + encodeURIComponent(valor)
    })
    .then(response => response.text())
    .then(data => {
        mostrarMensaje(data);
        nombreMateria.value = "";
        desactivarBotones();
        cargarMaterias(); // recarga la tabla automáticamente
    })
    .catch(error => console.error("Error:", error));
}

// --- Modificar materia ---
function modificarMateria() {

    const nuevoNombre = nombreMateria.value.trim();

    fetch("../../php/Materias-LAGP/ModificarMateria.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "nombreOriginal=" + encodeURIComponent(nombreOriginal) +
              "&nuevoNombre=" + encodeURIComponent(nuevoNombre)
    })
    .then(response => response.text())
    .then(data => {
        mostrarMensaje(data);
        nombreMateria.value = "";
        nombreOriginal = null; // Limpiamos selección
        cargarMaterias();
    })
    .catch(error => console.error("Error:", error));
    detenerProceso();
}

// ---Eliminar Materia ---
function eliminarMateria() {
    
    fetch("../../php/Materias-LAGP/EliminarMateria.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "nombreOriginal=" + encodeURIComponent(nombreOriginal)
    })
    .then(response => response.text())
    .then(data => {
        mostrarMensaje(data);
        nombreMateria.value = "";
        nombreOriginal = null; // Limpiamos selección
        cargarMaterias();
    })
    .catch(error => console.error("Error:", error));
    detenerProceso();
}

// Ejecutar al cargar la página
nombreMateria.value = '';
desactivarBotones();
window.onload = cargarMaterias;
