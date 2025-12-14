#!/bin/sh

# 1. Ejecutar migraciones (crear tablas)
php artisan migrate --force

# 2. Generar claves para el login
php artisan passport:keys --force

# 3. Arrancar el servidor Apache (IMPORTANTE)
apache2-foreground
