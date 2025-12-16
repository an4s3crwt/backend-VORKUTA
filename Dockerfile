# =========================================================
# BASE IMAGE
# =========================================================
FROM php:8.2-apache

# =========================================================
# 1️⃣ DEPENDENCIAS DEL SISTEMA
# =========================================================
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libpq-dev \
    libzip-dev \
    zip \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# =========================================================
# 2️⃣ EXTENSIONES PHP NECESARIAS
# =========================================================
RUN docker-php-ext-install pdo pdo_pgsql bcmath zip
RUN pecl install redis && docker-php-ext-enable redis

# =========================================================
# 3️⃣ COMPOSER
# =========================================================
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# =========================================================
# 4️⃣ WORKDIR
# =========================================================
WORKDIR /var/www/html

# =========================================================
# 5️⃣ CACHE DE DEPENDENCIAS COMPOSER
# =========================================================
COPY composer.json composer.lock ./
RUN composer install --no-interaction --optimize-autoloader --no-dev

# =========================================================
# 6️⃣ COPIAR PROYECTO
# =========================================================
COPY . .

# =========================================================
# 7️⃣ PERMISOS LARAVEL
# =========================================================
RUN chown -R www-data:www-data storage bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

# =========================================================
# 8️⃣ APACHE
# =========================================================
RUN a2enmod rewrite
RUN sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:8080>/g' /etc/apache2/sites-available/000-default.conf
COPY vhost.conf /etc/apache2/sites-available/000-default.conf
EXPOSE 8080

# =========================================================
# 9️⃣ SCRIPT DE ARRANQUE
# =========================================================
COPY render_start.sh /usr/local/bin/render_start.sh
RUN chmod +x /usr/local/bin/render_start.sh
CMD ["/usr/local/bin/render_start.sh"]

# =========================================================
# 10️⃣ LIMPIEZA FINAL (Reduce tamaño de imagen)
# =========================================================
RUN apt-get clean && rm -rf /var/lib/apt/lists/*
