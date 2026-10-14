# Guía de estudio completa: PHP + Docker + MongoDB Atlas

Este documento está pensado para que puedas explicar **todo** el proyecto, línea por línea, cuando el profesor te lo pregunte.

Incluye:
- comandos de terminal
- paso del contenedor básico a la solución final
- explicación del `Dockerfile`
- explicación de `docker-compose.yml`
- instalación de Composer
- por qué fallaba al principio
- cómo se recondujo
- explicación de `index.php`
- explicación de `resultados.php`
- preguntas típicas del profesor

---

# 0. Estado inicial del proyecto

Al principio había un contenedor básico:

```bash
docker run -d -p 8080:80 --name php-validaciones -v C:\Users\anten\Documents\practica-servidor-validacion:/var/www/html php:8.3-apache
```

Ese contenedor solo servía PHP 8.3 con Apache.  
Problemas iniciales:

1. No tenía Composer.
2. No tenía la extensión `mongodb`.
3. No podía conectarse a MongoDB Atlas.
4. El intento de conexión a Mongo se había hecho mal desde JavaScript.

Comandos para ver contenedores:

```bash
docker container ls
```

Comando para ver también los detenidos:

```bash
docker ps -a
```

Comando para ver logs:

```bash
docker logs php-validaciones
```

Comando para inspeccionar el contenedor:

```bash
docker inspect php-validaciones -f "Mounts: {{json .Mounts}}; Env: {{json .Config.Env}}; Cmd: {{json .Config.Cmd}}; WorkDir: {{.Config.WorkingDir}}"
```

---

# 1. Por qué se pasó de contenedor suelto a Dockerfile + docker-compose

## Opción antigua: contenedor suelto

```bash
docker run ...
```

Problema: si reinicias el PC o borras el contenedor, la configuración se pierde.

## Opción nueva: Dockerfile + docker-compose

Ventaja: la configuración queda guardada en el repositorio.  
Cualquiera puede levantar el mismo entorno con:

```bash
docker compose up -d --build
```

---

# 2. Dockerfile explicado línea por línea

Archivo: `Dockerfile`

```dockerfile
FROM php:8.3-apache
```

- `FROM`: indica la imagen base.
- `php:8.3-apache`: PHP 8.3 con Apache preinstalado.
- Es una imagen oficial de Docker Hub.

```dockerfile
RUN apt-get update && apt-get install -y --no-install-recommends \
    libmongoc-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*
```

- `RUN`: ejecuta un comando al construir la imagen.
- `apt-get update`: actualiza lista de paquetes de Debian.
- `apt-get install -y`: instala sin pedir confirmación.
- `--no-install-recommends`: instala solo lo necesario.
- `libmongoc-dev`: librería necesaria para compilar la extensión PHP de MongoDB.
- `git`: necesario para que Composer pueda instalar paquetes.
- `unzip`: necesario para descomprimir paquetes de Composer.
- `rm -rf /var/lib/apt/lists/*`: limpia caché para que la imagen pese menos.

```dockerfile
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb
```

- `pecl`: instalador de extensiones PHP.
- `pecl install mongodb`: compila e instala la extensión MongoDB de PHP.
- `docker-php-ext-enable mongodb`: activa la extensión creando un archivo `.ini` en `/usr/local/etc/php/conf.d/`.
- Sin esto, PHP no tiene la clase `MongoDB\Client`.

```dockerfile
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
```

- `COPY --from=composer:2`: copia archivos desde otra imagen de Docker.
- Copia el binario de Composer desde la imagen oficial `composer:2`.
- No hace falta instalar Composer manualmente.

```dockerfile
WORKDIR /var/www/html
```

- Establece el directorio de trabajo dentro del contenedor.
- Apache sirve archivos desde `/var/www/html`.

```dockerfile
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --optimize-autoloader
```

- Copia `composer.json` al contenedor.
- `composer.lock*`: copia el lock si existe.
- `RUN composer install`: instala las dependencias PHP.
- `--no-dev`: no instala dependencias de desarrollo.
- `--no-interaction`: no pregunta nada.
- `--optimize-autoloader`: genera autoload más rápido.

```dockerfile
COPY . .
```

- Copia todo el proyecto al contenedor.

```dockerfile
RUN chown -R www-data:www-data /var/www/html
```

- Cambia el propietario de los archivos a `www-data`.
- Apache suele ejecutarse como `www-data`.

```dockerfile
RUN printf 'ServerName localhost\n' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername
```

- Crea un archivo de configuración de Apache.
- Establece `ServerName localhost`.
- Evita el aviso `AH00558`.
- `a2enconf`: activa esa configuración.

---

# 3. docker-compose.yml explicado línea por línea

Archivo: `docker-compose.yml`

```yaml
services:
  web:
```

- Define un servicio llamado `web`.
- `services`: agrupa contenedores.

```yaml
    build:
      context: .
```

- Indica que la imagen se construye usando el `Dockerfile` de la carpeta actual.

```yaml
    container_name: php-validaciones
```

- Nombre del contenedor.

```yaml
    ports:
      - "8080:80"
```

- Mapea el puerto 8080 del host al puerto 80 del contenedor.
- `host:contenedor`.

```yaml
    env_file:
      - .env
```

- Carga variables de entorno desde `.env`.
- Así, PHP puede leer `MONGODB_URI`, `MONGODB_DB` y `MONGODB_COLLECTION`.

```yaml
    volumes:
      - .:/var/www/html
      - vendor:/var/www/html/vendor
```

- `.:/var/www/html`: monta el código fuente del host dentro del contenedor para desarrollo.
- `vendor:/var/www/html/vendor`: crea un volumen anónimo para `vendor`.
- Esto evita que el bind mount pise las dependencias instaladas por Composer.

```yaml
    restart: unless-stopped
```

- Si el contenedor se detiene, se reinicia automáticamente, salvo que se haya detenido manualmente.

```yaml
volumes:
  vendor:
```

- Declara el volumen anónimo compartido para `vendor`.

---

# 4. composer.json explicado

Archivo: `composer.json`

```json
{
    "name": "anteneh/practica-servidor-validacion",
    "description": "Validacion de datos en PHP y envio a MongoDB Atlas",
    "require": {
        "php": ">=8.1",
        "mongodb/mongodb": "^2.0"
    },
    "config": {
        "optimize-autoloader": true,
        "sort-packages": true
    }
}
```

- `name`: nombre del paquete.
- `description`: descripción.
- `require`: dependencias obligatorias.
- `php`: >= 8.1, compatible con PHP 8.3.
- `mongodb/mongodb`: librería oficial para MongoDB.
- `^2.0`: permite 2.x pero no 3.x.
- `optimize-autoloader`: acelera carga de clases.
- `sort-packages`: ordena paquetes al guardar.

---

# 5. Variables de entorno

Archivo: `.env` (no se sube al repo)

```env
MONGODB_URI=mongodb+srv://antenehro_db_user:antenehrocha@cluster0.rfzgpbk.mongodb.net/?appName=Cluster0
MONGODB_DB=formulario-php
MONGODB_COLLECTION=Usuarios
```

- `MONGODB_URI`: URI de conexión a Atlas.
- `MONGODB_DB`: base de datos.
- `MONGODB_COLLECTION`: colección.
- `mongodb+srv://`: formato SRV de MongoDB Atlas.

Archivo: `.env.example`

```env
MONGODB_URI=mongodb+srv://USUARIO:PASSWORD@cluster.mongodb.net/?retryWrites=true&w=majority
MONGODB_DB=formulario-php
MONGODB_COLLECTION=Usuarios
```

- Plantilla para que otras personas sepan qué variables configurar.

---

# 6. Instalación de Composer

Composer no se instaló en el host.  
Se copió desde la imagen oficial:

```dockerfile
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
```

Después se usó:

```bash
composer install
```

Dentro del Dockerfile, con:

```dockerfile
RUN composer install --no-dev --no-interaction --optimize-autoloader
```

Esto descarga `mongodb/mongodb` en `vendor/`.

---

# 7. Por qué fallaba al principio

Había dos problemas principales.

## Problema 1: intento de conexión desde JavaScript

En una versión anterior había:

```javascript
import mongodb from 'mongodb';
import { MongoClient } from 'mongodb';

const client = new MongoClient('mongodb://localhost:27017');
```

Eso intentaba usar MongoDB desde el navegador. Eso no funcionaba porque:
- el navegador no puede abrir la conexión como servidor
- Atlas requiere autenticación
- el código cliente no tenía acceso al driver real de la misma forma

La solución fue hacer la conexión desde PHP con:

```php
$client = new MongoDB\Client($uri);
```

## Problema 2: incompatibilidad de versiones

El error de compilación más importante fue:

```text
Root composer.json requires mongodb/mongodb ^1.20
mongodb/mongodb 1.21.5 requires ext-mongodb ^1.21.0
it has the wrong version installed (2.5.4)
```

Explicación:
- `pecl install mongodb` instaló la extensión PHP `2.5.4`
- el paquete PHP `mongodb/mongodb` `1.x` pedía la extensión `1.x`

## Solución aplicada

Se cambió el `composer.json` de:

```json
"mongodb/mongodb": "^1.20"
```

a:

```json
"mongodb/mongodb": "^2.0"
```

Así, el paquete PHP 2.x es compatible con la extensión PHP 2.x.

---

# 8. Terminología importante que el profesor puede preguntar

## `FROM`

Indica la imagen base.

```dockerfile
FROM php:8.3-apache
```

## `RUN`

Ejecuta un comando al construir la imagen.

```dockerfile
RUN apt-get update
```

## `COPY`

Copia archivos del host o de otra imagen al contenedor.

```dockerfile
COPY . .
```

## `WORKDIR`

Cambia el directorio actual dentro del contenedor.

```dockerfile
WORKDIR /var/www/html
```

## `ENV`

No se usa directamente, pero `env_file` en Compose sí carga variables.

## `VOLUME`

No se declara explícitamente en Dockerfile, pero Compose crea un volumen `vendor`.

## `ENTRYPOINT`

No se define manualmente. La imagen base `php:8.3-apache` ya trae su entrypoint.

## `CMD`

Tampoco se define manualmente. La imagen base ejecuta Apache.

---

# 9. Comandos principales del proyecto

## Ver contenedores

```bash
docker container ls
```

## Ver contenedores detenidos también

```bash
docker ps -a
```

## Levantar proyecto

```bash
docker compose up -d --build
```

- `-d`: modo detached, en background.
- `--build`: fuerza reconstrucción.

## Parar proyecto

```bash
docker compose down
```

## Parar y borrar volúmenes

```bash
docker compose down -v
```

## Ver logs

```bash
docker compose logs -f
```

## Ver estado

```bash
docker compose ps
```

## Comprobar extensión MongoDB

```bash
docker exec php-validaciones php -m | findstr mongodb
```

## Comprobar sintaxis PHP

```bash
docker exec php-validaciones php -l /var/www/html/resultados.php
```

## Entrar a un contenedor

```bash
docker exec -it php-validaciones bash
```

## Dentro del contenedor, probar PHP

```bash
php -v
php -m
php -r "echo extension_loaded('mongodb') ? 'mongodb OK' : 'NO mongodb';"
```

## Probar conexión a Atlas desde dentro del contenedor

```bash
docker exec php-validaciones php -r "require '/var/www/html/vendor/autoload.php'; \$c = new MongoDB\Client(getenv('MONGODB_URI')); echo 'cliente creado';"
```

---

# 10. index.php explicado línea por línea

Archivo actual: `index.php`

```php
<?php
```

- Abre el bloque PHP.
- Todo lo que sigue será PHP hasta `?>`.

```php
session_start();
```

- Inicia la sesión PHP.
- Permite compartir datos entre `index.php` y `resultados.php`.

```php
$errores = [
    'nombre' => '',
    'apellidos' => '',
    'dni' => '',
    'e-mail' => ''
];
```

- Crea un array asociativo.
- Cada clave representa un campo del formulario.
- El valor es el mensaje de error.
- Al inicio está vacío, así que no hay errores.

```php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
```

- Comprueba si el formulario fue enviado por POST.
- Si es GET, no ejecuta la validación.

```php
$nombre = trim($_POST['nombre']);
$apellidos = trim($_POST['apellidos']);
$dni = trim($_POST['dni']);
$e_mail = trim($_POST['e-mail']);
```

- Recoge los datos del formulario.
- `trim()` quita espacios al principio y al final.

```php
if (empty($nombre)) {
    $errores['nombre'] = "introduce un nombe";
}
```

- Si el nombre está vacío, agrega error.
- Hay un typo intencional: "nombe" sin r.
- No afecta al código porque es un string de texto.

```php
if (empty($apellidos)) {
    $errores['apellidos'] = "introduce un apelido";
}
```

- Igual para apellidos.
- Typo intencional.

```php
if (empty($dni)) {
    $errores['dni'] = "introduce un dni";
}
```

- Valida que el DNI no esté vacío.

```php
if (empty($e_mail)) {
    $errores['e-mail'] = "introduce un email";
}
```

- Valida que el email no esté vacío.

```php
if (!filter_var($_POST['e-mail'], FILTER_VALIDATE_EMAIL)) {
    $errores['e-mail'] = "el email no es valido";
}
```

- `filter_var()` valida formato de email.
- Si no es válido, sobreescribe el error.

```php
if (empty(array_filter($errores))) {
```

- `array_filter()` elimina valores vacíos.
- Si el array final está vacío, no hay errores.
- Entonces los datos son válidos.

```php
$_SESSION['usuario'] = [
    'nombre' => $nombre,
    'apellidos' => $apellidos,
    'dni' => $dni,
    'e-mail' => $e_mail
];
```

- Guarda los datos en la sesión.
- Así pueden usarse en `resultados.php`.

```php
unset($_SESSION['mongo_guardado']);
```

- Reinicia el estado de guardado.
- Permite que un nuevo envío se guarde otra vez.

```php
header('Location: ./resultados.php');
exit;
```

- Redirige a resultados.
- `exit` detiene la ejecución.

```php
<script>
    console.log(<?php echo json_encode($errores); ?>);
</script>
```

- Imprime los errores en la consola del navegador para depuración.

```php
<form action="index.php" method="POST">
```

- Formulario HTML que envía datos por POST a `index.php`.

```php
<label> nombre </label>
<input type="text" name="nombre">
```

- Campo visible.
- `name` es la clave que llega en `$_POST`.

```php
<?php foreach ($errores as $error): ?>
    <?php if (!empty($error)): ?>
        <p><?php echo $error; ?></p>
    <?php endif; ?>
<?php endforeach; ?>
```

- Recorre los errores.
- Solo muestra los que no están vacíos.

---

# 11. resultados.php explicado línea por línea

Archivo actual: `resultados.php`

```php
<?php
session_start();
```

- Inicia sesión.
- Necesario para leer `$_SESSION['usuario']`.

```php
if (empty($_SESSION['usuario'])) {
    header('Location: ./index.php');
    exit;
}
```

- Evita entrar directamente a resultados sin validar datos.
- Si no hay sesión, vuelve al formulario.

```php
$usuario = $_SESSION['usuario'];
```

- Guarda los datos en una variable para no repetir `$_SESSION` todo el rato.

```php
$uri = getenv('MONGODB_URI');
$db = getenv('MONGODB_DB');
$coleccion = getenv('MONGODB_COLLECTION');
```

- Lee las variables del entorno.
- `getenv()` obtiene variables de entorno del contenedor.

```php
$mensaje = '';
```

- Mensaje que se mostrará en pantalla.

```php
if (empty($_SESSION['mongo_guardado'])) {
```

- Comprueba si ya se guardó.
- Si está vacío, todavía no se ha guardado.

```php
require __DIR__ . '/vendor/autoload.php';
```

- Carga el autoloader de Composer.
- Necesario para usar `MongoDB\Client`.

```php
$cliente = new MongoDB\Client($uri);
$tabla = $cliente->selectDatabase($db)->selectCollection($coleccion);
```

- Crea el cliente MongoDB.
- Selecciona base de datos.
- Selecciona colección.

```php
$tabla->insertOne([
    'nombre' => $usuario['nombre'],
    'apellidos' => $usuario['apellidos'],
    'dni' => $usuario['dni'],
    'email' => $usuario['e-mail'],
]);
```

- Inserta un documento en la colección.
- El documento es un array asociativo.
- Las claves serán campos en MongoDB.

```php
$_SESSION['mongo_guardado'] = true;
$mensaje = 'datos guardados en mongodb atlas.';
```

- Marca que ya se guardó.
- Evita duplicados al recargar.

```php
catch (Throwable $e) {
    $mensaje = 'error al guardar los datos: ' . $e->getMessage();
}
```

- Si falla la conexión o inserción, captura el error.
- Muestra el mensaje.

```php
else {
    $mensaje = 'estos datos ya se habian guardado antes.';
}
```

- Si ya estaba guardado, no inserta de nuevo.

```php
$nombre = htmlspecialchars((string) $usuario['nombre'], ENT_QUOTES, 'UTF-8');
$apellidos = htmlspecialchars((string) $usuario['apellidos'], ENT_QUOTES, 'UTF-8');
$dni = htmlspecialchars((string) $usuario['dni'], ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars((string) $usuario['e-mail'], ENT_QUOTES, 'UTF-8');
```

- Escapa los datos antes de mostrarlos en HTML.
- `ENT_QUOTES`: convierte comillas simples y dobles.
- Previene XSS.

```php
<p>hola <?php echo $nombre . ' ' . $apellidos; ?>, datos validados corectamente.</p>
```

- Muestra saludo.
- Typo intencional: "corectamente".

```php
<p>dni: <?php echo $dni; ?></p>
<p>e-mail: <?php echo $email; ?></p>
```

- Muestra DNI y email.

```php
<p><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></p>
```

- Muestra mensaje de estado.

```php
<p><a href="index.php">volver al formulario</a></p>
```

- Enlace para volver.

---

# 12. Flujo completo del programa

```text
1. El usuario abre http://localhost:8080
2. Apache ejecuta index.php
3. Se inicia la sesión
4. Se muestra el formulario
5. El usuario envía datos
6. index.php valida cada campo
7. Si hay errores, se muestran
8. Si no hay errores, se guardan en $_SESSION
9. Se redirige a resultados.php
10. resultados.php lee la sesión
11. Carga el autoloader
12. Crea MongoDB\Client
13. Selecciona base de datos y colección
14. insertOne guarda el documento
15. Se marca como guardado
16. Se muestra el mensaje
```

---

# 13. Cómo se pasó del contenedor básico a la solución final

## Situación inicial

Contenedor:
```bash
docker run ... php:8.3-apache
```

Problemas:
- no Composer
- no extensión mongodb
- no conexión Atlas
- JavaScript intentaba conectar a Mongo

## Paso 1: añadir Dockerfile

Se creó un Dockerfile con:
- PHP 8.3 Apache
- libmongoc-dev
- pecl mongodb
- Composer
- composer install

## Paso 2: añadir docker-compose

Se creó Compose para:
- build
- puertos
- env
- volúmenes

## Paso 3: añadir composer.json

Se definió:
```json
"mongodb/mongodb": "^2.0"
```

## Paso 4: corregir resultados.php

Se reemplazó JavaScript por PHP:
```php
$client = new MongoDB\Client($uri);
```

## Paso 5: resolver error de versiones

Se cambió de MongoDB 1.x a 2.x.

## Paso 6: probar

Se levantó:
```bash
docker compose up -d --build
```

Se envió el formulario y se obtuvo:
```text
datos guardados en mongodb atlas.
```

---

# 14. Alternativas para hacerlo de otra manera

## Alternativa 1: instalar Composer manualmente en el contenedor

En lugar de:
```dockerfile
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
```

se podría hacer dentro del contenedor:
```bash
docker exec -it php-validaciones bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

Malestar:
- menos reproducible
- se pierde si se borra el contenedor

## Alternativa 2: instalar PECL mongodb versión 1.x

En lugar de usar `mongodb/mongodb ^2.0`, se podría fijar una versión antigua de la extensión:

```dockerfile
RUN pecl install mongodb-1.21.0
```

y usar:
```json
"mongodb/mongodb": "^1.20"
```

Problema:
- más antiguo
- no es la versión que instala PECL por defecto ahora

## Alternativa 3: usar Atlas Data API

En lugar de PHP + extensión mongodb, se podría usar HTTP REST de Atlas.

Pero sería:
- menos nativo
- más lento
- más complejo para autenticación

## Alternativa 4: usar MongoDB local en Docker

En lugar de Atlas, se podría levantar un contenedor Mongo:

```yaml
services:
  mongo:
    image: mongo:7
    ports:
      - "27017:27017"
```

Y URI:
```text
mongodb://mongo:27017
```

Ventaja:
- no depende de internet
- útil para desarrollo

## Alternativa 5: no usar docker-compose, solo Dockerfile

Se podría construir y correr manualmente:
```bash
docker build -t mi-php .
docker run -p 8080:80 --env-file .env mi-php
```

Pero Compose es más cómodo.

---

# 15. Preguntas típicas del profesor

## ¿Qué es un Dockerfile?

Es un archivo de texto que define cómo construir una imagen Docker.

## ¿Qué hace `pecl install mongodb`?

Instala la extensión PHP para MongoDB.

## ¿Por qué se necesitaba Composer?

Porque la conexión a Atlas se hizo con la librería oficial `mongodb/mongodb`.

## ¿Por qué falló el build al principio?

Por incompatibilidad entre:
- extensión PECL `2.5.4`
- paquete PHP `mongodb/mongodb` `1.x`

## ¿Cómo se solucionó?

Cambiando el `composer.json` a:
```json
"mongodb/mongodb": "^2.0"
```

## ¿Qué hace `docker compose up -d --build`?

Construye la imagen y levanta el contenedor en segundo plano.

## ¿Qué es un volumen anónimo?

Es un volumen gestionado por Docker para conservar datos entre reconstrucciones.  
Aquí se usa para `vendor`.

## ¿Por qué no se sube `.env`?

Porque contiene secretos.  
Se usa `.env.example` como plantilla.

## ¿Qué hace `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`?

Escapa caracteres especiales para evitar XSS y problemas de HTML.

## ¿Cómo se conecta a Atlas?

Con la URI del `.env` y:
```php
$client = new MongoDB\Client($uri);
```

## ¿Qué es SRV?

Es un tipo de DNS que MongoDB Atlas usa para resolver el cluster.

## ¿Por qué se usa `trim()`?

Para quitar espacios sobrantes del formulario.

## ¿Por qué `session_start()`?

Para compartir datos entre páginas sin usar la URL.

---

# 16. Resumen final para el examen oral

Si el profesor pregunta: "Explícame todo lo que has hecho", puedes responder así:

> Primero tenía un contenedor básico `php:8.3-apache`. Intenté conectar a MongoDB Atlas desde JavaScript, pero eso no era correcto. Después creé un Dockerfile para instalar la extensión `mongodb` de PHP y Composer. Instalé la librería oficial `mongodb/mongodb`. Al principio falló porque la extensión PECL era la 2.x y el paquete Composer era la 1.x. Lo solucioné cambiando a `^2.0`. También creé un `docker-compose.yml` para levantar el servicio con variables de entorno y volúmenes. En `index.php` valido el formulario y guardo los datos en sesión. En `resultados.php` uso `MongoDB\Client`, selecciono base de datos y colección, y llamo a `insertOne`. Por último, pruebo y se guarda correctamente en Atlas.

---

# 17. Archivos finales del proyecto

```text
index.php
resultados.php
Dockerfile
docker-compose.yml
composer.json
.env
.env.example
.gitignore
.dockerignore
README.md
LOGICA.md
ESTUDIO.md
```

---

# 18. Nota sobre typos intencionales

Se introdujeron algunos typos intencionales **solo en textos visibles**, por ejemplo:

- `nombe`
- `apelido`
- `corectamente`
- textos en minúsculas

No afectan al código porque son cadenas de texto de la interfaz.

---

# 19. Último comando sugerido antes de enseñarlo al profesor

```bash
docker compose down -v
docker compose up -d --build
```

Luego abrir:

```text
http://localhost:8080
```

Enviar el formulario y comprobar que aparece:

```text
datos guardados en mongodb atlas.
```