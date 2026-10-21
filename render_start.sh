#!/bin/bash
set -e

echo "--> Preparando entorno de producción..."

# 1. Asegurar directorio de almacenamiento y permisos
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 2. Restaurar credenciales de Firebase si se pasan como variable de entorno
# (En Render puedes crear una variable llamada FIREBASE_CREDENTIALS_JSON con el contenido del archivo)
if [ ! -f "storage/credentials.json" ] && [ -n "$FIREBASE_CREDENTIALS_JSON" ]; then
    echo "--> Generando storage/credentials.json desde variable de entorno..."
    echo "$FIREBASE_CREDENTIALS_JSON" > storage/credentials.json
    chmod 600 storage/credentials.json
fi

# 3. Descubrimiento de paquetes y assets
echo "--> Registrando paquetes de Laravel..."
php artisan package:discover --ansi || true
php artisan vendor:publish --tag=laravel-assets --ansi --force || true

# 4. Migraciones seguras (sin borrar datos existentes)
echo "--> Ejecutando migraciones de base de datos..."
php artisan migrate --force || echo "ADVERTENCIA: Las migraciones no se pudieron completar en el arranque."

# 5. Optimización de rutas y configuración para producción
echo "--> Optimizando caché de Laravel..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# 6. Arrancar Apache
echo "--> Servidor listo. Arrancando Apache en puerto 8080..."
exec apache2-foreground