function mostrarElemento() {
  const select = document.getElementById('ComboTipoRegistro');
  const elemento1 = document.getElementById('comboCarreras');
  const elemento2 = document.getElementById('spanCarreras');

  if (select.value === '3') { //3 es el value: del comboBox
    elemento1.style.display = 'block';
    elemento2.style.display = 'block';
  } else {
    elemento1.style.display = 'none';
    elemento2.style.display = 'none';
  }
}

function validar() {
  const input = document.getElementById("numeroControl");
  
  if (!input.checkValidity()) {
    alert("❌ Valor inválido: " + input.title);
    return
  }
}