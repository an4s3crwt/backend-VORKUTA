FROM php:8.2-apache

# --- DEPENDENCIAS DEL SISTEMA Y PHP ---
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP necesarias (Ejemplo para PostgreSQL)
RUN docker-php-ext-install pdo pdo_pgsql

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar directorio de trabajo
WORKDIR /var/www/html

# --- COPIA EXPLÍCITA Y INSTALACIÓN DE DEPENDENCIAS ---
COPY composer.json composer.lock ./
RUN composer install --no-interaction --optimize-autoloader --no-dev --no-scripts
COPY . .

# --- CONFIGURACIÓN DE LARAVEL Y APACHE ---

# Permisos para storage y cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Habilitar mod_rewrite de Apache para Laravel
RUN a2enmod rewrite

# Copiar configuración de VirtualHost (CRÍTICO: apunte a public/)
COPY vhost.conf /etc/apache2/sites-available/000-default.conf

# 1. Copiar y dar permisos al script de arranque
COPY render_start.sh /usr/local/bin/render_start.sh
RUN chmod +x /usr/local/bin/render_start.sh

# 2. Reemplazar el CMD (entrypoint) predeterminado con nuestro script
CMD ["/usr/local/bin/render_start.sh"]