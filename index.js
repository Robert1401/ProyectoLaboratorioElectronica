const mysql = require('mysql2');

// Configuración de la conexión
const conexion = mysql.createConnection({
  host: '127.0.0.1',     // Servidor MySQL
  user: 'root',          // Usuario de MySQL
  password: 'root', // Cambia por tu contraseña real
  database: 'universidad'  // Nombre de tu base de datos
});

// Conectar a MySQL
conexion.connect((err) => {
  if (err) {
    console.error('Error de conexión:', err);
    return;
  }
  console.log("✅ Conectado a MySQL!");

  // Hacer una consulta
  conexion.query("SELECT * FROM alumnos", (err, results) => {
    if (err) {
      console.error('Error en la consulta:', err);
      return;
    }
    console.log("📌 Resultados de la tabla alumnos:");
    console.log(results);

    // Cerrar la conexión
    conexion.end();
  });
});
