FROM php:8.2-cli

# gd: imágenes (fotos y firmas) dentro del informe PDF de la fase 4.
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install pdo pdo_pgsql zip gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# OPcache también para el servidor de "php artisan serve": sin él, cada solicitud vuelve a
# leer y compilar vendor/ desde el volumen montado (muy lento en Docker Desktop para Windows).
# Revalidar cada 60 s evita revisar miles de archivos en cada solicitud (de ~2,8 s a ~0,3 s);
# un cambio en PHP puede tardar hasta 60 s en verse dentro del contenedor (o reinicie el servicio app).
RUN (docker-php-ext-enable opcache || docker-php-ext-install opcache) \
    && printf '%s\n' \
       'opcache.enable=1' \
       'opcache.enable_cli=1' \
       'opcache.memory_consumption=192' \
       'opcache.max_accelerated_files=20000' \
       'opcache.validate_timestamps=1' \
       'opcache.revalidate_freq=60' \
       'realpath_cache_size=4096K' \
       'realpath_cache_ttl=600' \
       > /usr/local/etc/php/conf.d/zz-sigma-opcache.ini

# Fotos de los ascensores: hasta 3 imágenes de 5 MB por solicitud.
RUN printf '%s\n' \
       'upload_max_filesize=6M' \
       'post_max_size=20M' \
       'max_file_uploads=10' \
       > /usr/local/etc/php/conf.d/zz-sigma-subidas.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000
