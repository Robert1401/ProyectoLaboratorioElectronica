// 🔹 Variables principales
const tabs = document.querySelectorAll(".tab");
const indicator = document.querySelector(".tab-indicator");
const loginForm = document.getElementById("loginForm");
const usuarioInput = document.getElementById("usuario");
const passwordInput = document.getElementById("password");

// Crear input oculto para rol
let rolInput = document.createElement("input");
rolInput.type = "hidden";
rolInput.id = "rol";
rolInput.value = "alumnos"; // default
loginForm.appendChild(rolInput);

// 🔹 Manejo de pestañas
tabs.forEach(tab => {
    tab.addEventListener("click", () => {
        document.querySelector(".tab.active").classList.remove("active");
        tab.classList.add("active");

        const index = [...tabs].indexOf(tab);
        indicator.style.transform = `translateX(${index * 100}%)`;

        rolInput.value = tab.getAttribute("data-tab");

        // Cambiar placeholder
        if (rolInput.value === "alumnos") usuarioInput.placeholder = "Usuario (Matrícula)";
        else if (rolInput.value === "auxiliar") usuarioInput.placeholder = "Usuario (ID Auxiliar)";
    });
});

// 🔹 Manejo del envío del formulario
loginForm.addEventListener("submit", function(event) {
    event.preventDefault();

    let usuario = usuarioInput.value.trim();
    const password = passwordInput.value.trim();
    const rol = rolInput.value;

    if (usuario === "" || password === "") {
        alert("⚠️ Por favor, complete todos los campos.");
        return;
    }

    // Validación de rol antes de enviar
    if (rol === "alumnos") {
        if (!/^\d{4,}$/.test(usuario)) {
            alert("❌ Solo se permiten alumnos en esta pestaña.");
            return;
        }
        usuario = parseInt(usuario, 10); // enviar como número
    } else if (rol === "auxiliar" && !/^aux/.test(usuario)) {
    alert("❌ Solo se permiten auxiliares en esta pestaña.");
    return;
}


    // 🔹 Enviar datos al servidor
    fetch('login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ usuario, password, rol })
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            if (data.rol === 'auxiliar') window.location.href = 'auxiliar.html';
            else if (data.rol === 'alumnos') window.location.href = 'alumnos.html';
        }
    })
    .catch(err => {
        alert("❌ Error al conectar con el servidor.");
        console.error(err);
    });
});
