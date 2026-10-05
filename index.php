<?php

session_start();

$errores = [
    'nombre' => '',
    'apellidos' => '',
    'dni' => '',
    'e-mail' => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $apellidos = trim($_POST['apellidos']);
    $dni = trim($_POST['dni']);
    $e_mail = trim($_POST['e-mail']);

    if (empty($nombre)) {
        $errores['nombre'] = "introduce un nombre";
    }
    if (empty($apellidos)) {
        $errores['apellidos'] = "introduce un apellido";
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

    if (empty(array_filter($errores))) {
        $_SESSION['usuario'] = [
            'nombre' => $nombre,
            'apellidos' => $apellidos,
            'dni' => $dni,
            'e-mail' => $e_mail
        ];
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
        <label> Nombre </label>
        <input type="text" name="nombre">

        <label> Apellidos </label>
        <input type="text" name="apellidos">

        <label> DNI </label>
        <input type="text" name="dni">

        <label> E-Mail </label>
        <input type="text" name="e-mail">

        <button type="submit"> Enviar </button>
    </form>
    <?php foreach ($errores as $error): ?>
        <?php if (!empty($error)): ?>
            <p><?php echo $error; ?></p>
        <?php endif; ?>
    <?php endforeach; ?>
</body>

</html>