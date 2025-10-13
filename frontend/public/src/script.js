document.getElementById("loginForm").addEventListener("submit", async (e) => {
  e.preventDefault();

  const usuario = document.getElementById("usuario").value.trim();
  const password = document.getElementById("password").value.trim();

  if (!usuario || !password) {
    alert("⚠️ Por favor, completa todos los campos.");
    return;
  }

  try {
   const res = await fetch("../../../backend/login.php", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({ usuario, password })
});


    const data = await res.json();
    alert(data.message);

    if (data.success) {
      if (data.rol === "auxiliar") {
        window.location.href = "../Auxiliar/auxiliar.html";
      } else if (data.rol === "alumno") {
        window.location.href = "../Alumnos/alumnos.html";
      }
    }
  } catch (err) {
    console.error(err);
    alert("❌ Error al conectar con el servidor.");
  }
});
