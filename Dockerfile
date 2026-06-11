# Use the official PHP 8.2 Apache image
FROM php:8.2-apache

# Install PostgreSQL client library and extensions
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo_pgsql pgsql

# Copy your entire PHP application into the container's web root
COPY . /var/www/html/

# Set correct permissions (Apache user is www-data)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Enable Apache mod_rewrite if needed (optional)
RUN a2enmod rewrite
