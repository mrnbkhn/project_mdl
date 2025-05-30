#!/bin/bash

# Wacht op de database
echo "Wachten op PostgreSQL..."
until pg_isready -h postgres -U moodle_user; do
  sleep 2
done

echo "Start Moodle CLI installatie..."
php admin/cli/install.php \
  --chmod=2770 \
  --lang=en \
  --wwwroot=${MOODLE_URL} \
  --dataroot=/var/www/moodledata \
  --dbtype=pgsql \
  --dbhost=postgres \
  --dbname=moodle \
  --dbuser=moodle_user \
  --dbpass=moodle_password \
  --fullname="${MOODLE_SITENAME}" \
  --shortname="moodle" \
  --adminuser="${MOODLE_ADMIN_USER}" \
  --adminpass="${MOODLE_ADMIN_PASS}" \
  --adminemail="${MOODLE_ADMIN_EMAIL}" \
  --agree-license \
  --non-interactive

echo "Upgrade plugins (indien aanwezig)..."
php admin/cli/upgrade.php --non-interactive

# Start apache (na installatie)
apache2-foreground

