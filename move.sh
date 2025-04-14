#!/bin/bash

# Crea le nuove directory
mkdir -p public/assets/css
mkdir -p public/assets/js
mkdir -p public/assets/images
mkdir -p public/partials
mkdir -p app/controllers
mkdir -p app/models
mkdir -p app/views
mkdir -p app/helpers
mkdir -p config
mkdir -p auth
mkdir -p admin

# Sposta i file PHP principali nella cartella 'public'
mv add_to_cart.php public/
mv cart.php public/
mv index.php public/
mv listing.php public/
mv marketplace.php public/
mv toggle_wishlist.php public/

# Sposta gli script JS e il CSS nella cartella assets
mv script.js public/assets/js/
mv style.css public/assets/css/

# Sposta le immagini (se presenti) nella cartella assets/images
if [ -d "image" ]; then
    mv image/* public/assets/images/ 2>/dev/null
    rmdir image
fi

# Sposta header e footer nei partials
mv header.php public/partials/
mv footer.php public/partials/

# Sposta i file di autenticazione nella cartella auth
mv config.php config/
mv login.php auth/
mv logout.php auth/
mv register.php auth/

# Sposta il file admin_stats nella cartella admin
mv admin_stats.php admin/

# Rimuove la cartella vuota
rmdir setting

# Conferma che il progetto è stato riorganizzato
echo "✅ Progetto riorganizzato con successo!"
