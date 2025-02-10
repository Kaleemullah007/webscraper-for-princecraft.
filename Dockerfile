# Start with the official PHP image with Apache
FROM php:8.2-apache

# Install additional PHP extensions and utilities
RUN apt-get update && apt-get install -y \ 
    libzip-dev \ 
    zip \ 
    unzip \ 
    curl \ 
    && docker-php-ext-install zip mysqli pdo pdo_mysql \ 
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite module (useful for frameworks like Laravel)
RUN a2enmod rewrite

# Set the working directory in the container
WORKDIR /var/www/html

# Copy application files into the container
# Make sure your PHP application files are in the "src" directory
# COPY . /var/www/html

# Set permissions for the web server
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

# Expose port 80 for the web server
EXPOSE 80

# Start Apache server
CMD ["apache2-foreground"]
