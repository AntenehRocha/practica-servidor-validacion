# Lógica completa del programa

Este documento explica cómo funciona internamente la aplicación, desde que el usuario abre el formulario hasta que los datos se guardan en MongoDB Atlas.

## 1. Objetivo del programa

La aplicación realiza dos tareas principales:

1. **Validar datos de un formulario en PHP**, ejecutándose dentro de un contenedor Docker con `php:8.3-apache`.
2. **Enviar los datos validados a MongoDB Atlas**, usando la librería oficial `mongodb/mongodb`.

La validación ocurre en el servidor, no en el navegador. MongoDB también se conecta desde PHP, no desde JavaScript.

## 2. Archivos principales y responsabilidad

### `index.php`

Responsable de:

- iniciar la sesión PHP
- inicializar los errores vacíos
- recibir datos por `POST`
- limpiar los datos recibidos
- validar cada campo
- si no hay errores:
  - guardar los datos en `$_SESSION['usuario']`
  - reiniciar el estado `mongo_guardado`
  - redirigir a `resultados.php`
- mostrar el formulario HTML
- mostrar errores de validación

### `resultados.php`

Responsable de:

- iniciar la sesión PHP
- impedir acceso directo sin datos validados
- leer los datos de `$_SESSION['usuario']`
- insertar los datos en MongoDB Atlas una sola vez
- mostrar el resultado del guardado
- mostrar los datos validados
- permitir volver al formulario

### `composer.json`

Define la dependencia PHP necesaria para Atlas:

```json
"mongodb/mongodb": "^2.0"
```

Es importante que sea la versión 2.x, porque la extensión MongoDB compilada con `pecl` es la versión 2.x.

### `Dockerfile`

Define la imagen del contenedor:

1. Usa `php:8.3-apache`
2. Instala dependencias del sistema
3. Instala y habilita la extensión PHP `mongodb`
4. Copia Composer
5. Instala dependencias PHP
6. Copia el código
7. Asigna permisos a Apache

### `docker-compose.yml`

Levanta el servicio:

- construye la imagen con el `Dockerfile`
- publica el puerto `8080:80`
- carga variables desde `.env`
- monta el código para desarrollo
- conserva `vendor` dentro de un volumen anónimo

### `.env`

Contiene:

```env
MONGODB_URI=mongodb+srv://USUARIO:PASSWORD@cluster.mongodb.net/?retryWrites=true&w=majority
MONGODB_DB=formulario-php
MONGODB_COLLECTION=Usuarios
```

Es un archivo local que no debe subirse al repositorio.

## 3. Flujo general de ejecución

```text
Navegador
   |
   v
http://localhost:8080
   |
   v
index.php muestra formulario
   |
   usuario envía datos
   |
   v
index.php recibe POST
   |
   limpia datos
   |
   valida campos
   |
   ¿hay errores?
   | sí -> muestra formulario + errores
   | no  -> guarda datos en sesión
           -> redirige a resultados.php
   |
   v
resultados.php lee sesión
   |
   ¿URI configurada?
   | no  -> avisa que falta configurar .env
   | sí  -> conecta a MongoDB Atlas
           -> inserta documento
           -> marca como guardado
   |
   v
muestra estado y datos
```

## 4. Lógica de `index.php` en detalle

### 4.1 Inicio de sesión

```php
session_start();
```

Se inicia la sesión para que los datos válidos puedan pasar de `index.php` a `resultados.php`.

### 4.2 Inicialización de errores

```php
$errores = [
    'nombre' => '',
    'apellidos' => '',
    'dni' => '',
    'e-mail' => ''
];
```

Cada campo tiene una posible cadena de error. Si queda vacía, significa que ese campo es válido.

### 4.3 Recepción de datos

```php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $apellidos = trim($_POST['apellidos']);
    $dni = trim($_POST['dni']);
    $e_mail = trim($_POST['e-mail']);
```

`trim()` elimina espacios al inicio y al final.

### 4.4 Validación de campos vacíos

```php
if (empty($nombre)) {
    $errores['nombre'] = "introduce un nombre";
}
```

Se repite para:

- nombre
- apellidos
- dni
- e-mail

Si alguno está vacío, se agrega un mensaje.

### 4.5 Validación de email

```php
if (!filter_var($_POST['e-mail'], FILTER_VALIDATE_EMAIL)) {
    $errores['e-mail'] = "el email no es valido";
}
```

`filter_var()` comprueba si el texto tiene formato de email válido.

### 4.6 Comprobación final de validación

```php
if (empty(array_filter($errores))) {
```

`array_filter()` elimina valores vacíos del array. Si el array resultante está vacío, significa que no hay errores.

Entonces:

```php
$_SESSION['usuario'] = [
    'nombre' => $nombre,
    'apellidos' => $apellidos,
    'dni' => $dni,
    'e-mail' => $e_mail
];
unset($_SESSION['mongo_guardado']);
header('Location: ./resultados.php');
exit;
```

Se guardan los datos en sesión, se reinicia el estado MongoDB y se redirige a la página de resultados.

Si hay errores, el script sigue y vuelve a mostrar el formulario con los mensajes.

## 5. Lógica de `resultados.php` en detalle

### 5.1 Protección de acceso

```php
if (empty($_SESSION['usuario'])) {
    header('Location: ./index.php');
    exit;
}
```

Esto evita que alguien entre directamente a `resultados.php` por URL sin haber validado datos.

### 5.2 Lectura de datos validados

```php
$usuario = $_SESSION['usuario'];
```

Contiene:

- nombre
- apellidos
- dni
- e-mail

### 5.3 Variables de control MongoDB

```php
$estadoMongo = 'pendiente';
$mensajeMongo = '';
$mongoId = null;
```

Se usan para mostrar al usuario qué ocurrió al guardar.

### 5.4 Guardado una sola vez

```php
if (empty($_SESSION['mongo_guardado'])) {
```

Esto evita insertar el mismo documento varias veces si el usuario recarga la página.

### 5.5 Lectura de variables de entorno

```php
$uri = trim((string) getenv('MONGODB_URI'));
$db = trim((string) getenv('MONGODB_DB'));
$collectionName = trim((string) getenv('MONGODB_COLLECTION'));
```

Actualmente no hay valores por defecto: `MONGODB_DB` y `MONGODB_COLLECTION` deben configurarse en `.env`.

Si falta alguno de estos valores, la aplicación no intenta conectar y muestra un mensaje de configuración incompleta.

### 5.6 Comprobación de placeholder

```php
$uriEsPlaceholder = $uri === ''
    || stripos($uri, 'USUARIO:PASSWORD') !== false
    || stripos($uri, 'usuario:password') !== false;
```

Si la URI no está configurada o sigue teniendo el placeholder, la aplicación avisa y no intenta conectar a Atlas.

### 5.7 Comprobación de extensión PHP

```php
elseif (!extension_loaded('mongodb')) {
```

Si la extensión no está cargada, se muestra un error.

### 5.8 Conexión e inserción

```php
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
```

Documentación del flujo:

1. Carga el autoloader de Composer.
2. Crea el cliente de MongoDB.
3. Selecciona la base de datos.
4. Selecciona la colección.
5. Inserta un documento con los datos validados.
6. Añade fecha de creación e IP.

Luego:

```php
$_SESSION['mongo_guardado'] = true;
$estadoMongo = 'ok';
$mongoId = (string) $resultado->getInsertedId();
```

Se marca como guardado para evitar duplicados.

### 5.9 Gestión de errores

```php
catch (Throwable $e) {
    $estadoMongo = 'error';
    $mensajeMongo = '... ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
```

Si Atlas falla, la aplicación no se rompe. Muestra el error sin exponer datos sensibles de forma directa en la respuesta HTML.

### 5.10 Ya guardado anteriormente

```php
else {
    $estadoMongo = 'ok';
    $mensajeMongo = 'Estos datos ya habian sido guardados anteriormente.';
}
```

Si el usuario recarga `resultados.php`, no se inserta de nuevo.

## 6. Lógica del contenedor Docker

### 6.1 Build del Dockerfile

```dockerfile
FROM php:8.3-apache
```

Se parte de la imagen oficial de PHP con Apache.

Luego:

```dockerfile
RUN apt-get update && apt-get install -y --no-install-recommends \
    libmongoc-dev \
    git \
    unzip
```

Se instalan:

- librerías necesarias para compilar la extensión MongoDB
- Git, útil para Composer
- unzip, útil para descomprimir paquetes

Después:

```dockerfile
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb
```

Esto compila e instala la extensión `mongodb` de PHP.

Luego se copia Composer:

```dockerfile
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
```

Y se instalan dependencias PHP:

```dockerfile
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --optimize-autoloader
```

Finalmente:

```dockerfile
COPY . .
RUN chown -R www-data:www-data /var/www/html
```

Se copia la aplicación y se asignan permisos al usuario de Apache.

### 6.2 docker-compose

```yaml
services:
  web:
    build:
      context: .
    container_name: php-validaciones
    ports:
      - "8080:80"
    env_file:
      - .env
    volumes:
      - .:/var/www/html
      - vendor:/var/www/html/vendor
```

Explicación:

- `build.context: .` construye la imagen con el `Dockerfile`.
- `ports: 8080:80` expone la app en `localhost:8080`.
- `env_file: .env` carga las variables de Atlas.
- `.:/var/www/html` monta el código para desarrollo.
- `vendor:/var/www/html/vendor` protege el directorio de dependencias.

## 7. Lógica de conexión a Atlas

La URI de MongoDB Atlas tiene esta forma:

```text
mongodb+srv://USUARIO:PASSWORD@cluster.mongodb.net/?retryWrites=true&w=majority
```

PHP la usa así:

```php
$client = new MongoDB\Client($uri);
```

La librería:

1. interpreta la URI
2. resuelve el cluster SRV
3. abre conexión TLS
4. autentica con usuario y contraseña
5. permite seleccionar base de datos y colección

La base de datos y la colección no necesitan crearse manualmente. MongoDB las crea cuando se inserta el primer documento.

## 8. Control de duplicados al recargar

Problema posible:

- El usuario envía formulario.
- Se inserta en Atlas.
- Si recarga `resultados.php`, no se debe insertar otra vez.

Solución usada:

- `$_SESSION['mongo_guardado']` se establece en `true` después de la inserción.
- Si existe, la aplicación no vuelve a insertar.
- Cuando llega un nuevo envío válido desde `index.php`, se hace:

```php
unset($_SESSION['mongo_guardado']);
```

Así el nuevo registro sí se guardará.

## 9. Seguridad básica aplicada

- `.env` no se sube al repo.
- Se documenta `.env.example` con placeholders.
- La salida de errores se escapa con `htmlspecialchars()`.
- La validación ocurre en servidor.
- La contraseña de Atlas no aparece en el código PHP.

## 10. Cómo depurar si algo falla

### Ver contenedores

```powershell
docker compose ps
```

### Ver logs

```powershell
docker compose logs -f
```

### Comprobar extensión MongoDB

```powershell
docker exec php-validaciones php -m | findstr mongodb
```

### Probar PHP sin exponer la URI

```powershell
docker exec php-validaciones php -r "echo getenv('MONGODB_URI') ? 'URI cargada correctamente' : 'No hay URI configurada';"
```

### Recrear todo

```powershell
docker compose down -v
docker compose up -d --build
```

## 11. Resumen final

La cadena completa es:

```text
Usuario
  -> Formulario HTML
  -> POST a index.php
  -> Validación PHP en contenedor
  -> $_SESSION
  -> Redirección a resultados.php
  -> Conexión MongoDB\Client
  -> Atlas insertOne
  -> ID insertado
  -> Respuesta HTML
```

Por eso el programa necesita:

- contenedor PHP
- extensión MongoDB
- Composer
- librería mongodb/mongodb
- variables de entorno
- sesión PHP
- Atlas correctamente configurado