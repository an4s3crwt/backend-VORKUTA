#!/bin/bash

# Este script SÍ lleva 'echo' y 'php artisan migrate'
echo "--> Iniciando: php artisan migrate:fresh --force"
php artisan migrate:fresh --force

echo "--> Limpiando caché de Laravel..."
php artisan cache:clear
php artisan route:clear
php artisan config:clear

echo "--> Iniciando el servidor Apache en foreground."
exec apache2-foreground