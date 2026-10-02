<?php
session_start();

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
    <p> Hola <span> <?php echo $_SESSION['usuario']['nombre'] . " " . $_SESSION['usuario']['apellidos']; ?> </span> </p>
    <p> bienvenido a casa </p>
</body>

</html>