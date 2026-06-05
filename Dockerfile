FROM php:8.2-apache

# 1. Install dependencies system dan ekstensi PHP
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql gd zip bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Aktifkan modul rewrite Apache untuk Laravel routing
RUN a2enmod rewrite

# 3. Salin konfigurasi VirtualHost Apache
COPY .docker/apache.conf /etc/apache2/sites-available/000-default.conf

# 4. Tentukan working directory
WORKDIR /var/www/html

# 5. Salin kode aplikasi
COPY . /var/www/html

# 6. Salin Composer binary dari image Composer resmi
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 7. Jalankan Composer Install
RUN composer install --no-interaction --optimize-autoloader --no-dev

# 8. Atur hak akses folder storage & bootstrap cache agar writable oleh web server
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 9. Ekspos port 80 untuk container
EXPOSE 80

# 10. Nyalakan server Apache
CMD ["apache2-foreground"]
