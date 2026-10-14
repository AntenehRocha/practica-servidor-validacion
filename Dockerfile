FROM php:8.3-apache

# Dependencias del sistema para compilar la extension mongodb y usar Composer
RUN apt-get update && apt-get install -y --no-install-recommends \
    libmongoc-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Extension mongodb de PHP (necesaria para conectarse a MongoDB Atlas)
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb

# Composer (desde la imagen oficial)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Instala las dependencias PHP antes de copiar el codigo (mejor cache de Docker)
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --optimize-autoloader

# Copia el codigo de la aplicacion
COPY . .

# Permisos para Apache
RUN chown -R www-data:www-data /var/www/html

# Suprime el aviso de ServerName
RUN printf 'ServerName localhost\n' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername
