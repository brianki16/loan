FROM php:8.3-apache

# Copy application files
COPY . /var/www/html/

# Enable Apache rewrite module
RUN a2enmod rewrite

EXPOSE 80

CMD ["apache2-foreground"]
