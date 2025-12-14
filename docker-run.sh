#!/bin/sh

# 1. Migraciones (con el truco de ignorar errores)
php artisan migrate --force || true

# 2. ¡LIMPIEZA OBLIGATORIA! (Esto hace lo que harías en el Shell)
php artisan cache:clear
php artisan route:clear
php artisan config:clear

# 3. Arrancar el servidor
apache2-foreground