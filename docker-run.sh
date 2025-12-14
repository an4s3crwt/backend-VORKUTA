#!/bin/sh

# 1. Intentamos migrar. El "|| true" significa: "Si fallas, NO PARES, sigue igual".
php artisan migrate --force || true

# 2. BORRA la linea de passport si aun esta ahi.
# (La hemos quitado para que no falle)

# 3. Arrancar el servidor
apache2-foreground