FROM php:8.2-apache

# ... (Todo tu código RUN apt-get install y docker-php-ext-install)

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos del proyecto
COPY . .

# Instalar dependencias de Laravel
RUN composer install --no-interaction --optimize-autoloader --no-dev --no-scripts

# Permisos para storage y cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Habilitar mod_rewrite de Apache para Laravel
RUN a2enmod rewrite

# --- INTEGRACIÓN DEL SCRIPT DE ARRANQUE ---

# 1. Copiar y dar permisos al script de arranque
COPY render_start.sh /usr/local/bin/render_start.sh
RUN chmod +x /usr/local/bin/render_start.sh

# 2. Reemplazar el CMD (entrypoint) predeterminado de Apache con nuestro script
# El script se ejecutará, hará las migraciones, y luego arrancará el servidor
CMD ["/usr/local/bin/render_start.sh"]