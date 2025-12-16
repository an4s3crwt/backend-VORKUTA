FROM php:8.2-apache

# --- DEPENDENCIAS DEL SISTEMA Y PHP ---
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP necesarias (PostgreSQL)
RUN docker-php-ext-install pdo pdo_pgsql

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar directorio de trabajo
WORKDIR /var/www/html

# --- COPIA EXPLÍCITA E INSTALACIÓN DE DEPENDENCIAS ---

# 1. Copiar archivos de definición de dependencias
COPY composer.json composer.lock ./

# 2. Copiar el resto del proyecto, incluyendo 'artisan'
COPY . . 

# 3. Instalar dependencias base y resolver Sanctum
RUN composer install --no-interaction --optimize-autoloader --no-dev

# 🛠️ PASO CRÍTICO: DESINSTALAR TELESCOPE 
RUN composer remove laravel/telescope --no-update

# CRÍTICO: Regenerar el autoloader sin ejecutar NINGÚN script.
# Esto evita que 'php artisan package:discover' falle durante el build.
RUN composer dump-autoload --optimize --no-scripts 


# --- CONFIGURACIÓN DE LARAVEL Y APACHE ---

# Permisos para storage y cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Habilitar mod_rewrite de Apache para Laravel
RUN a2enmod rewrite

# 🛠️ AJUSTE DE PUERTOS: Forzar Apache a escuchar en 8080
RUN sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:8080>/g' /etc/apache2/sites-available/000-default.conf

# Copiar configuración de VirtualHost 
COPY vhost.conf /etc/apache2/sites-available/000-default.conf

# Exponer el puerto de escucha
EXPOSE 8080 

# 1. Copiar y dar permisos al script de arranque
COPY render_start.sh /usr/local/bin/render_start.sh
RUN chmod +x /usr/local/bin/render_start.sh

# 2. Reemplazar el CMD (entrypoint) predeterminado con nuestro script
CMD ["/usr/local/bin/render_start.sh"]