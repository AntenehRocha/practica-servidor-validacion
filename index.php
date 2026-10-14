<?php

/**
 * aqui creo la session para luego poder almacenar el objeto de los datos introducidos en el formulario
 * 
 * se me ocurrió crear un objeto de errores para unificar los errores en un mismo lugar
 * 
 */

session_start();

$errores = [
    'nombre' => '',
    'apellidos' => '',
    'dni' => '',
    'e-mail' => ''
];

/**
 * aqui es importante decirle que atienda a los POST para que cada vez que se de click al boton de enviar recoja los datos y los valide
 */

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $apellidos = trim($_POST['apellidos']);
    $dni = trim($_POST['dni']);
    $e_mail = trim($_POST['e-mail']);

    /**
     * aqui compruebo que los campos no estén vacios
     * 
     */

    if (empty($nombre)) {
        $errores['nombre'] = "introduce un nombe";
    }
    if (empty($apellidos)) {
        $errores['apellidos'] = "introduce un apelido";
    }
    if (empty($dni)) {
        $errores['dni'] = "introduce un dni";
    }
    if (empty($e_mail)) {
        $errores['e-mail'] = "introduce un email";
    }

    if (!filter_var($_POST['e-mail'], FILTER_VALIDATE_EMAIL)) {
        $errores['e-mail'] = "el email no es valido";
    }

    /**
     * si el objeto de los errores está vacio iguala session a usuario con los datos
     * y envia a resultados.php
     */

    if (empty(array_filter($errores))) {
        $_SESSION['usuario'] = [
            'nombre' => $nombre,
            'apellidos' => $apellidos,
            'dni' => $dni,
            'e-mail' => $e_mail
        ];
        // Nuevo intento de guardado para estos datos
        unset($_SESSION['mongo_guardado']);
        header('Location: ./resultados.php');
        exit;
    }
}
?>

<script>
    console.log(<?php echo json_encode($errores); ?>);
</script>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>formulario</title>
</head>

<body>
    <form action="index.php" method="POST">
        <label> nombre </label>
        <input type="text" name="nombre">

        <label> apellidos </label>
        <input type="text" name="apellidos">

        <label> dni </label>
        <input type="text" name="dni">

        <label> e-mail </label>
        <input type="text" name="e-mail">

        <button type="submit"> enviar </button>
    </form>
    <!-- esto lo pongo para mostrar en el html los errores de validación -->
    <?php foreach ($errores as $error): ?>
        <?php if (!empty($error)): ?>
            <p><?php echo $error; ?></p>
        <?php endif; ?>
    <?php endforeach; ?>
</body>

</html>