#!/bin/bash
set -e

echo "=== 2FA Manager - PHP Setup ==="

# Install PHP extensions only if not already present
if ! php -m 2>/dev/null | grep -qi ldap; then
    echo "Installing system packages..."
    apt-get update -qq > /dev/null
    apt-get install -y -qq \
        libldap2-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libzip-dev libsodium-dev unzip git curl > /dev/null 2>&1

    echo "Installing PHP extensions..."
    docker-php-ext-configure gd --with-freetype --with-jpeg > /dev/null 2>&1
    docker-php-ext-install -j"$(nproc)" ldap gd zip sodium > /dev/null 2>&1
fi

# Enable Apache modules
a2enmod rewrite > /dev/null 2>&1

# Configure Apache
cat > /etc/apache2/sites-available/000-default.conf << 'APACHE_CONF'
<VirtualHost *:80>
    DocumentRoot /var/www/app/public
    <Directory /var/www/app/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
APACHE_CONF

# Install Composer if not present
if [ ! -f /usr/local/bin/composer ]; then
    echo "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer 2>/dev/null
fi

# Install PHP dependencies
cd /var/www/app
if [ ! -f vendor/autoload.php ]; then
    echo "Installing PHP dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction 2>&1
fi

# Ensure backups directory is writable by www-data
mkdir -p /backups
chown www-data:www-data /backups
chmod 775 /backups

if [ -f /scheduler/backup-scheduler.php ]; then
    echo "Starting backup scheduler in background..."
    su -s /bin/sh www-data -c "php /scheduler/backup-scheduler.php >> /proc/1/fd/1 2>> /proc/1/fd/2" &
fi

echo "=== Setup complete — starting Apache ==="
exec apache2-foreground
