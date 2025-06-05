#!/bin/bash
set -e

if [ ! -f /var/www/html/moodle/config.php ]; then
    echo "Start Moodle installatie..."
    /var/www/html/moodle/install-moodle.sh || exit 1
else
    echo "Moodle is al geïnstalleerd, doorgaan met apache..."
fi

# Pas wwwroot aan in config.php (optioneel als je dat al had)
echo "Pas wwwroot aan in config.php..."
sed -i "s|^\$CFG->wwwroot.*|\$CFG->wwwroot = 'http://localhost/moodle';|" /var/www/html/moodle/config.php

# Zet permissies en eigenaar van config.php
echo "Zet permissies en eigenaar voor config.php..."
chmod 644 /var/www/html/moodle/config.php
chown www-data:www-data /var/www/html/moodle/config.php

# Voeg ServerName toe als die ontbreekt
if ! grep -q "ServerName" /etc/apache2/apache2.conf; then
    echo "ServerName localhost" >> /etc/apache2/apache2.conf
fi

# Start Apache
exec apache2-foreground

