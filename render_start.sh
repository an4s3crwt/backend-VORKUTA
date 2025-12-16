#!/bin/bash

# 1. Ejecutar las migraciones (Solución del error 500 inicial)
echo "--> Iniciando: php artisan migrate:fresh --force"
php artisan migrate:fresh --force

# 2. Limpieza de Caché y Configuración
echo "--> Limpiando caché de Laravel..."
php artisan cache:clear
php artisan route:clear
php artisan config:clear

# 3. Arrancar el servidor Apache en FOREGROUND (CRÍTICO para Render/Docker)
echo "--> Iniciando el servidor Apache en foreground."
exec apache2-foreground