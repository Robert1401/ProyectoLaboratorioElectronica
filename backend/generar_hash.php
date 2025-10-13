<?php
$clave = "7890"; // Contraseña deseada
$hash = password_hash($clave, PASSWORD_BCRYPT);
echo $hash;
?>
