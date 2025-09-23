# Gunakan PHP-FPM image dengan ekstensi yang dibutuhkan
FROM php:8.2-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    git \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql

# Install ekstensi mongodb via pecl
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb

# Set working directory
WORKDIR /var/www/laravel

# Copy file Laravel ke dalam container
COPY . .

# Salin file .env jika perlu (opsional)
 COPY .env.example .env

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install dependencies Laravel
RUN composer install

RUN php artisan storage:link

# Pastikan folder storage dan bootstrap/cache bisa ditulis
RUN chmod -R 777 storage bootstrap/cache

# Expose port untuk PHP-FPM
EXPOSE 9001

CMD ["php-fpm"]
