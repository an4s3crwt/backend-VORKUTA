FROM php:8.2-apache

# --- DEPENDENCIAS DEL SISTEMA Y PHP ---
# (Mantén aquí todos tus comandos RUN apt-get install y docker-php-ext-install)
# EJEMPLO:
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    # Limpieza
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones PHP necesarias (Ejemplo para PostgreSQL)
RUN docker-php-ext-install pdo pdo_pgsql

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar directorio de trabajo
WORKDIR /var/www/html

# --- COPIA EXPLÍCITA Y INSTALACIÓN DE DEPENDENCIAS (CLAVE PARA EL ERROR 1) ---

# 1. Copiar solo los archivos de definición de dependencias
# Esto permite que Docker cachee esta capa si los archivos no cambian.
COPY composer.json composer.lock ./

# 2. Instalar dependencias de Laravel
# Si el fallo persiste, elimina el flag --no-scripts (si tienes scripts custom en composer.json)
RUN composer install --no-interaction --optimize-autoloader --no-dev --no-scripts

# 3. Copiar el resto de archivos del proyecto (solo después de instalar dependencias)
COPY . .

# --- CONFIGURACIÓN DE LARAVEL Y ARRANQUE ---

# Permisos para storage y cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Habilitar mod_rewrite de Apache para Laravel
RUN a2enmod rewrite

# 1. Copiar y dar permisos al script de arranque
COPY render_start.sh /usr/local/bin/render_start.sh
RUN chmod +x /usr/local/bin/render_start.sh

# 2. Reemplazar el CMD (entrypoint) predeterminado con nuestro script
# El script se ejecutará, hará las migraciones (solucionando el 500) y luego arrancará el servidor.
CMD ["/usr/local/bin/render_start.sh"]