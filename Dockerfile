#!/bin/bash

# 1. Ejecutar las migraciones (Solución del 500)
echo "--> Iniciando: php artisan migrate:fresh --force"
php artisan migrate:fresh --force

# 2. Limpieza de Caché y Configuración
echo "--> Limpiando caché de Laravel..."
php artisan cache:clear
php artisan route:clear
php artisan config:clear

# 3. Arrancar el servidor Apache en FOREGROUND (CRÍTICO)
echo "--> Iniciando el servidor Apache en foreground."

# El comando 'exec' es crucial, ya que reemplaza el shell actual con el 
# comando de Apache, asegurando que sea el proceso principal (PID 1).
exec apache2-foreground