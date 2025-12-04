FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libsnmp-dev \
    snmp \
    python3 \
    python3-pip \
    python3-psycopg2 \
    cron \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) \
       bcmath intl pcntl pdo_pgsql pgsql zip gd snmp \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Configurar límites de PHP para subida de archivos
RUN echo "upload_max_filesize = 20M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 25M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

CMD ["php-fpm"]

