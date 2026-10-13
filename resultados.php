<?php
session_start();

/**
 * si la session esta vacia directamente te redirige a index, así evito entradas forzadas por la url
 */

if (empty($_SESSION['usuario'])) {
    header('Location: ./index.php');
    exit;
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>resultados</title>
</head>

<body>
    <!-- aquí muestro los datos de la session -->
    <p> Hola <span> <?php echo $_SESSION['usuario']['nombre'] . " " . $_SESSION['usuario']['apellidos']; ?> </span> todos los datos han sido validados correctamente </p>
</body>

</html>