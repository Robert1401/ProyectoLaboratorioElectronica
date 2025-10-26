document.addEventListener("DOMContentLoaded", function() {
    const tabla = document.querySelector("#tablaMateriales tbody");

    // Mostrar materiales al cargar la página
    mostrarMateriales();

    document.getElementById("btnGuardar").addEventListener("click", () => {
        enviarDatos("agregar");
    });

    document.getElementById("btnActualizar").addEventListener("click", () => {
        enviarDatos("actualizar");
    });

    document.getElementById("btnEliminar").addEventListener("click", () => {
        enviarDatos("eliminar");
    });

    function enviarDatos(accion) {
        const datos = new FormData();
        datos.append("accion", accion);
        datos.append("clave", document.getElementById("clave").value);
        datos.append("nombre", document.getElementById("nombre").value);
        datos.append("cantidad", document.getElementById("cantidad").value);

        fetch("materiales.php", {
            method: "POST",
            body: datos
        })
        .then(res => res.text())
        .then(mensaje => {
            alert(mensaje);
            mostrarMateriales();
            limpiarCampos();
        });
    }

    function mostrarMateriales() {
        fetch("materiales.php", {
            method: "POST",
            body: new URLSearchParams({ accion: "listar" })
        })
        .then(res => res.json())
        .then(datos => {
            tabla.innerHTML = "";
            datos.forEach(mat => {
                const fila = document.createElement("tr");
                fila.innerHTML = `
                    <td>${mat.nombre}</td>
                    <td>${mat.clave}</td>
                    <td>${mat.cantidad}</td>
                `;
                tabla.appendChild(fila);
            });
        });
    }

    function limpiarCampos() {
        document.getElementById("clave").value = "";
        document.getElementById("nombre").value = "";
        document.getElementById("cantidad").value = "";
    }
});
