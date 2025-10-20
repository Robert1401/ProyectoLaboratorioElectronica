let nombreOriginal = null; // Guardaremos el nombre actual de la fila seleccionada
// Cargar Tabla
function cargarMaterias() {
    fetch('../../../backend/Materias-LAGP/TablaMaterias.php')
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
                        document.getElementById("nombreMateria").value = materia.nombre; // cargamos input
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
    const input = document.getElementById("nombreMateria");
    const valor = input.value.trim(); //Guarda el contenido en una variable y quita espacios al principio y final

    if (valor === "") {
        alert("El campo no puede estar vacío");
        return;
    }

    fetch("../../../backend/Materias-LAGP/GuardarMateria.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "nombre=" + encodeURIComponent(valor)
    })
    .then(response => response.text())
    .then(data => {
        alert(data);
        input.value = "";
        cargarMaterias(); // recarga la tabla automáticamente
    })
    .catch(error => console.error("Error:", error));
}

// --- Modificar materia ---
function modificarMateria() {
    if (!nombreOriginal) {
        alert("Selecciona primero una materia de la tabla");
        return;
    }

    const input = document.getElementById("nombreMateria");
    const nuevoNombre = input.value.trim();

    if (nuevoNombre === "") {
        alert("El campo no puede estar vacío");
        return;
    }

    fetch("../../../backend/Materias-LAGP/ModificarMateria.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "nombreOriginal=" + encodeURIComponent(nombreOriginal) +
              "&nuevoNombre=" + encodeURIComponent(nuevoNombre)
    })
    .then(response => response.text())
    .then(data => {
        alert(data);
        input.value = "";
        nombreOriginal = null; // Limpiamos selección
        cargarMaterias();
    })
    .catch(error => console.error("Error:", error));
}

// ---Eliminar Materia ---
function eliminarMateria() {
    if (!nombreOriginal) {
        alert("Selecciona primero una materia de la tabla");
        return;
    }

    const input = document.getElementById("nombreMateria");
    
    fetch("../../../backend/Materias-LAGP/EliminarMateria.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "nombreOriginal=" + encodeURIComponent(nombreOriginal)
    })
    .then(response => response.text())
    .then(data => {
        alert(data);
        input.value = "";
        nombreOriginal = null; // Limpiamos selección
        cargarMaterias();
    })
    .catch(error => console.error("Error:", error));
}

//Variables
const btn = document.getElementById("Guardar");
if (btn) btn.addEventListener("click", guardarMateria);

const btnModificar = document.getElementById("Modificar");
if (btnModificar) btnModificar.addEventListener("click", modificarMateria);

const btnEliminar = document.getElementById("Eliminar");
if (btnEliminar) btnEliminar.addEventListener("click", eliminarMateria);

// Ejecutar al cargar la página
window.onload = cargarMaterias;
