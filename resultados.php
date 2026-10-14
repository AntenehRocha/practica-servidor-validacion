<?php
session_start();

/**
 * Evita entradas forzadas por la URL: sin datos validados se vuelve al formulario.
 */
if (empty($_SESSION['usuario'])) {
    header('Location: ./index.php');
    exit;
}

$usuario = $_SESSION['usuario'];

$estadoMongo = 'pendiente';
$mensajeMongo = '';
$mongoId = null;

/**
 * Guarda los datos en MongoDB Atlas una sola vez por envio.
 * Si la URI todavia tiene el placeholder, solo avisa y no intenta conectar.
 */
if (empty($_SESSION['mongo_guardado'])) {
    $uri = trim((string) getenv('MONGODB_URI'));
    $db = trim((string) getenv('MONGODB_DB'));
    $collectionName = trim((string) getenv('MONGODB_COLLECTION'));

    if (!extension_loaded('mongodb')) {
        $estadoMongo = 'error';
        $mensajeMongo = 'La extension mongodb de PHP no esta habilitada en el contenedor.';
    } else {
        try {
            require __DIR__ . '/vendor/autoload.php';

            $client = new MongoDB\Client($uri);
            $collection = $client->selectDatabase($db)->selectCollection($collectionName);

            $resultado = $collection->insertOne([
                'nombre' => $usuario['nombre'],
                'apellidos' => $usuario['apellidos'],
                'dni' => $usuario['dni'],
                'email' => $usuario['e-mail'],
                'creadoEn' => new MongoDB\BSON\UTCDateTime(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            $_SESSION['mongo_guardado'] = true;
            $estadoMongo = 'ok';
            $mongoId = (string) $resultado->getInsertedId();
            $mensajeMongo = 'Datos validados y guardados en MongoDB Atlas.';
        } catch (Throwable $e) {
            $estadoMongo = 'error';
            $mensajeMongo = 'Los datos son validos, pero no se pudieron guardar en Atlas: '
                . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
} else {
    $estadoMongo = 'ok';
    $mensajeMongo = 'Estos datos ya habian sido guardados anteriormente.';
}

$nombre = htmlspecialchars((string) $usuario['nombre'], ENT_QUOTES, 'UTF-8');
$apellidos = htmlspecialchars((string) $usuario['apellidos'], ENT_QUOTES, 'UTF-8');
$dni = htmlspecialchars((string) $usuario['dni'], ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars((string) $usuario['e-mail'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados</title>
</head>
<body>
    <p>
        Hola <span><?php echo $nombre . ' ' . $apellidos; ?></span>,
        todos los datos han sido validados correctamente.
    </p>

    <p>DNI: <?php echo $dni; ?></p>
    <p>E-mail: <?php echo $email; ?></p>

    <p><strong>Estado MongoDB:</strong> <?php echo htmlspecialchars($estadoMongo, ENT_QUOTES, 'UTF-8'); ?></p>
    <p><?php echo $mensajeMongo; ?></p>

    <?php if ($mongoId !== null): ?>
        <p>ID insertado: <?php echo htmlspecialchars($mongoId, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <p><a href="index.php">Volver al formulario</a></p>
</body>
</html>
