# Base image
FROM php:8.2-fpm

# Install dependency like git, vim, supervisor and cron
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    libzip-dev \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    libonig-dev \
    supervisor \
    cron \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy source code, except path inside .dockerignore
COPY --chown=www-data:www-data . /var/www

# Setup supervisord
COPY --chown=www-data:www-data ./docker/supervisor/laravel-workers.conf /etc/supervisor/conf.d/laravel-workers.conf
COPY --chown=www-data:www-data ./docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

# Setup cron
COPY ./docker/cron/crontab /etc/cron.d/laravel-cron
RUN chmod 0644 /etc/cron.d/laravel-cron && \
    crontab /etc/cron.d/laravel-cron && \
    touch /var/log/cron.log && \
    chmod 0666 /var/log/cron.log

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN cd /var/www && composer install --optimize-autoloader --no-dev

# Set working directory for every command after this, for example docker exec -it will start from this directory
WORKDIR /var/www
