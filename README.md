# Práctica: validación de datos y envío a MongoDB Atlas

Aplicación PHP que valida un formulario en un contenedor Docker con `php:8.3-apache` y, si los datos son válidos, los guarda en MongoDB Atlas usando la librería oficial `mongodb/mongodb`.

## Estructura del proyecto

```text
practica-servidor-validacion/
├── Dockerfile
├── docker-compose.yml
├── composer.json
├── .env.example
├── index.php
├── resultados.php
└── README.md
```

## Requisitos

- Docker Desktop instalado y en ejecución
- Cuenta de MongoDB Atlas
- Cluster de MongoDB Atlas configurado
- Credenciales de un usuario de base de datos de Atlas

## Variables de entorno

Crea un archivo `.env` a partir de `.env.example`:

```powershell
Copy-Item .env.example .env
```

Edita `.env`:

```env
MONGODB_URI=mongodb+srv://USUARIO:PASSWORD@cluster.mongodb.net/?retryWrites=true&w=majority
MONGODB_DB=formulario-php
MONGODB_COLLECTION=Usuarios
```

> El archivo `.env` no se sube al repositorio.

## Arranque en local

```powershell
cd C:\Users\anten\Documents\practica-servidor-validacion
docker compose up -d --build
```

La aplicación queda disponible en:

```text
http://localhost:8080
```

## Flujo de la aplicación

1. `index.php` muestra el formulario.
2. Al enviar, PHP valida los campos en el contenedor.
3. Si hay errores, se muestran en la misma página.
4. Si todo es válido, los datos se guardan en `$_SESSION`.
5. Se redirige a `resultados.php`.
6. `resultados.php` inserta el documento una sola vez en MongoDB Atlas.
7. Se muestra el estado y el ID insertado.

## Dockerfile

La imagen incluye:
- Base `php:8.3-apache`
- Dependencias del sistema para compilar la extensión MongoDB
- Extensión PHP `mongodb`
- Composer
- Dependencias PHP instaladas con Composer
- Permisos para Apache

## docker-compose.yml

Incluye:
- Puerto `8080:80`
- Variables de entorno desde `.env`
- Montaje del código para desarrollo
- Volumen anónimo para `vendor`, evitando que el bind mount pise las dependencias

## Qué fallaba antes y cómo se resolvió

### Problema

El proyecto intentaba usar JavaScript para conectarse a MongoDB:

```javascript
import mongodb from 'mongodb';
import { MongoClient } from 'mongodb';
```

Eso no funciona en este contexto, porque la conexión a Atlas debe hacerse desde PHP en el servidor, no desde el navegador.

### Compilación/dependencias

También había un problema de versiones:
- `pecl install mongodb` instala la extensión PECL **2.x**.
- `mongodb/mongodb` **1.x** exige la extensión **1.x**.

### Solución aplicada

- Se eliminó el JavaScript incorrecto de `resultados.php`.
- Se instaló Composer en la imagen.
- Se instaló y habilitó la extensión PHP `mongodb`.
- Se usó `mongodb/mongodb ^2.0`, compatible con la extensión instalada.
- Se conecta desde PHP con:

```php
$client = new MongoDB\Client($uri);
$collection = $client->selectDatabase($db)->selectCollection($collectionName);
$collection->insertOne([...]);
```

## Qué hacer en el PC de clase

1. Clona el repositorio:

```bash
git clone https://github.com/AntenehRocha/practica-servidor-validacion.git
cd practica-servidor-validacion
```

2. Crea el archivo `.env`:

```bash
cp .env.example .env
```

En Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

3. Completa `.env` con tus credenciales reales de Atlas.

4. Asegúrate de que Docker Desktop esté en ejecución.

5. Levanta el contenedor:

```powershell
docker compose up -d --build
```

6. Abre en el navegador:

```text
http://localhost:8080
```

7. Rellena el formulario y envía.

8. Verifica en Atlas la base de datos `formulario-php` y la colección `Usuarios`.

## Comandos útiles

Ver contenedores:

```powershell
docker compose ps
```

Ver logs:

```powershell
docker compose logs -f
```

Recrear todo desde cero:

```powershell
docker compose down -v
docker compose up -d --build
```

Comprobar extensión MongoDB:

```powershell
docker exec php-validaciones php -m | findstr mongodb
```

Comprobar que la URI está cargada sin mostrarla completa:

```powershell
docker exec php-validaciones php -r "echo getenv('MONGODB_URI') ? 'URI cargada correctamente' : 'No hay URI configurada';"
```

## Seguridad

- No subas `.env` al repositorio.
- Usa `.env.example` con placeholders.
- Restringe las IPs en MongoDB Atlas cuando el proyecto esté terminado.
- No compartas la contraseña de Atlas.