#!/bin/bash

set -euxo pipefail

echo "Wachten op PostgreSQL..."
until pg_isready -h postgres -U moodle_user; do
  sleep 2
done

if [ ! -f config.php ]; then
  echo "Start Moodle CLI installatie..."
  php /var/www/html/moodle/admin/cli/install.php \
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

  if [ $? -ne 0 ]; then
    echo "Installatie mislukt. Stop."
    exit 1
  fi
fi

echo "Upgrade plugins (indien aanwezig)..."
php /var/www/html/moodle/admin/cli/upgrade.php --non-interactive

exit 0
