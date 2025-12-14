#!/bin/sh

# 1. Ejecutar migraciones (crear tablas)
php artisan migrate --force

# (AQUÍ HABÍA UNA LÍNEA DE PASSPORT, LA HAS BORRADO)

# 3. Arrancar el servidor Apache
apache2-foreground