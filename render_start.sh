#!/bin/bash

# Migraciones y Limpieza
php artisan migrate:fresh --force
php artisan cache:clear
php artisan route:clear
php artisan config:clear

# Arrancar el servidor y mantener el contenedor vivo
php -S 0.0.0.0:$PORT -t public