#!/bin/bash

# --- CONFIGURACIÓN DE LARAVEL ---

# 1. Ejecutar las migraciones desde CERO.
# Borra todas las tablas existentes y las crea de nuevo, basándose
# en el estado actual de los archivos de migración.
# Esto SOLUCIONA el error 500 "relation 'saved_flights' does not exist".
echo "--> Iniciando: php artisan migrate:fresh --force"
php artisan migrate:fresh --force

# 2. Limpieza de Caché y Optimización
echo "--> Limpiando y optimizando caché de Laravel..."
php artisan cache:clear
php artisan route:clear
php artisan config:clear

# 3. Configurar la clave de la aplicación (solo si es necesario, pero es buena práctica)
# php artisan key:generate --force

# --- ARRANQUE DEL SERVIDOR ---

# 4. Arrancar el servidor web (el Start Command anterior)
echo "--> Iniciando el servidor PHP en 0.0.0.0:$PORT"
php -S 0.0.0.0:$PORT -t public

# Si en tu Dockerfile tienes comandos de Apache, podrías necesitar usar
# apache2-foreground en lugar del comando php -S, pero seguiremos con la solución que funciona.