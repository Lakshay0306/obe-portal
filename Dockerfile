FROM php:8.3-apache

# Install PostgreSQL dependencies and PDO extension for database connection
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Copy the public folder contents to the Apache web root
COPY public/ /var/www/html/

# Expose port 80
EXPOSE 80
