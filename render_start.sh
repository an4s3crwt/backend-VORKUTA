#!/bin/bash

# 0. Ejecutar scripts de Composer faltantes (CRÍTICO: Lo que fallaba en el Dockerfile)
echo "--> Ejecutando scripts de Composer..."
php artisan package:discover --ansi 
php artisan vendor:publish --tag=laravel-assets --ansi --force

# 1. Ejecutar las migraciones
echo "--> Iniciando: php artisan migrate:fresh --force"
php artisan migrate:fresh --force

# 2. Limpieza de Caché y Configuración
echo "--> Limpiando caché de Laravel..."
php artisan cache:clear
php artisan route:clear
php artisan config:clear

# 3. Arrancar el servidor Apache en FOREGROUND
echo "--> Iniciando el servidor Apache en foreground (Puerto 8080)."
exec apache2-foreground