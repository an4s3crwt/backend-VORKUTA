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
# Dockerfile (Línea 29, aproximadamente)

# 2. Instalar dependencias de Laravel
# Quitamos flags restrictivos y regeneramos autoloader.
RUN composer install --no-interaction --optimize-autoloader --no-dev

# 3. Regenerar el autoloader de clases por si acaso (aunque optimize-autoloader ya lo hace)
RUN composer dump-autoload --optimize
COPY . .

# --- CONFIGURACIÓN DE LARAVEL Y APACHE ---

# Permisos para storage y cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Habilitar mod_rewrite de Apache para Laravel
RUN a2enmod rewrite

# 🛠️ AJUSTE CRÍTICO DE PUERTOS: Forzar Apache a escuchar en 8080
# 1. Cambiar Listen 80 a Listen 8080 en ports.conf
RUN sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf
# 2. Cambiar <VirtualHost *:80> a <VirtualHost *:8080> en la configuración por defecto
RUN sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:8080>/g' /etc/apache2/sites-available/000-default.conf

# Copiar configuración de VirtualHost (Asegura que apunta a /var/www/html/public)
COPY vhost.conf /etc/apache2/sites-available/000-default.conf

# Exponer el puerto de escucha (Render usará el PORT env var para mapear)
EXPOSE 8080 

# 1. Copiar y dar permisos al script de arranque
COPY render_start.sh /usr/local/bin/render_start.sh
RUN chmod +x /usr/local/bin/render_start.sh

# 2. Reemplazar el CMD (entrypoint) predeterminado con nuestro script
CMD ["/usr/local/bin/render_start.sh"]