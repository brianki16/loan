# Use the official PHP 8.2 Apache image as the base
FROM php:8.2-apache

# Update package lists and install the PostgreSQL client library (libpq-dev)
RUN apt-get update && apt-get install -y libpq-dev

# Install both the pdo_pgsql and pgsql PHP extensions
RUN docker-php-ext-install pdo_pgsql pgsql

# Enable the extensions in the PHP configuration
RUN docker-php-ext-enable pdo_pgsql pgsql

# Use the production-ready Apache configuration
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
