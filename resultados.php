<?php
session_start();

// Si no hay datos validados, vuelvo al formulario
if (empty($_SESSION['usuario'])) {
    header('Location: ./index.php');
    exit;
}

$usuario = $_SESSION['usuario'];

// Leo las variables del archivo .env
$uri = getenv('MONGODB_URI');
$db = getenv('MONGODB_DB');
$coleccion = getenv('MONGODB_COLLECTION');

$mensaje = '';

// Guardo solo una vez, aunque se recargue la pagina
if (empty($_SESSION['mongo_guardado'])) {
    require __DIR__ . '/vendor/autoload.php';

    // Conecto a Atlas y guardo el documento
    $cliente = new MongoDB\Client($uri);
    $tabla = $cliente->selectDatabase($db)->selectCollection($coleccion);

    try {
        $tabla->insertOne([
            'nombre' => $usuario['nombre'],
            'apellidos' => $usuario['apellidos'],
            'dni' => $usuario['dni'],
            'email' => $usuario['e-mail'],
        ]);

        $_SESSION['mongo_guardado'] = true;
        $mensaje = 'datos guardados en mongodb atlas.';
    } catch (Throwable $e) {
        $mensaje = 'error al guardar los datos: ' . $e->getMessage();
    }
} else {
    $mensaje = 'estos datos ya se habian guardado antes.';
}

// Limpio los datos para mostrarlos en HTML
$nombre = htmlspecialchars((string) $usuario['nombre'], ENT_QUOTES, 'UTF-8');
$apellidos = htmlspecialchars((string) $usuario['apellidos'], ENT_QUOTES, 'UTF-8');
$dni = htmlspecialchars((string) $usuario['dni'], ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars((string) $usuario['e-mail'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>resultados</title>
</head>

<body>
    <p>hola <?php echo $nombre . ' ' . $apellidos; ?>, datos validados corectamente.</p>

    <p>dni: <?php echo $dni; ?></p>

    <p>e-mail: <?php echo $email; ?></p>

    <p><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></p>

    <p><a href="index.php">volver al formulario</a></p>
</body>

</html>