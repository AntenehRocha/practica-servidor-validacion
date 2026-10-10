<?php
session_start();

if (empty($_SESSION['usuario'])) {
    header('Location: ./index.php');
    exit;
}


?>

<script>
    import mongodb from 'mongodb';
    import { MongoClient } from 'mongodb';

    const client = new MongoClient('mongodb://localhost:27017');
    const db = client.db('practica');
    const collection = db.collection('usuarios');

    const nombre = document.getElementById('nombre').value;
    const apellidos = document.getElementById('apellidos').value;
    const dni = document.getElementById('dni').value;
    const e_mail = document.getElementById('e-mail').value;

    const usuario = {
        nombre: nombre,
        apellidos: apellidos,
        dni: dni,
        e_mail: e_mail
    };

    

</script>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>resultados</title>
</head>

<body>
    <p> Hola <span> <?php echo $_SESSION['usuario']['nombre'] . " " . $_SESSION['usuario']['apellidos']; ?> </span> todos los datos han sido validados correctamente </p>
</body>

</html>