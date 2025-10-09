# ⚙️ Proyecto de Gestión del Laboratorio de Electrónica

Este proyecto tiene como objetivo administrar el préstamo, registro y control de materiales en el **Laboratorio de Electrónica Analógica** de la universidad.  
El sistema incluye un **backend** (servidor y base de datos) y un **frontend** (interfaz visual para los usuarios).

---

## 🧩 Estructura del Proyecto

ProyectoLaboratorioElectronica/
│
├── backend/ # Código del servidor (PHP, consultas a BD, controladores)
│ ├── app/
│ ├── controllers/
│ ├── database/
│ ├── models/
│ ├── routes/
│ ├── alumnos.php
│ ├── carreras.php
│ └── ...
│
├── frontend/ # Interfaz visual (HTML, CSS, JS)
│ ├── public/
│ │ ├── index.html
│ │ ├── styles.css
│ │ └── imágenes, páginas HTML
│ └── src/
│ ├── script.js
│ ├── registro.js
│ └── archivos JSON / JS adicionales
│
├── docs/ # Diagramas y documentación del sistema
│ ├── Diseño del Sistema.png
│ ├── Gestión de Asesorías.png
│ └── Solicitud de Materiales.png
│
└── README.md


---

## 🖥️ Tecnologías Utilizadas

### **Backend**
- PHP 8+
- MySQL / MariaDB
- Apache (XAMPP o WAMP)

### **Frontend**
- HTML5, CSS3, JavaScript

### **Control de versiones**
- Git y GitHub

---

## 🧠 Instalación (Local)

1. Clonar el repositorio:  
   ```bash
   git clone https://github.com/Robert1401/ProyectoLaboratorioElectronica.git
   cd ProyectoLaboratorioElectronica

Subir tus cambios
git add .
git commit -m "Actualización en formulario de registro"
git push origin nombre_de_tu_rama