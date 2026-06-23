FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    git unzip curl ca-certificates \
    libpng-dev libonig-dev libxml2-dev \
    libzip-dev libpq-dev \
    && update-ca-certificates \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql mbstring exif pcntl bcmath gd zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get update && apt-get install -y nodejs \
    && npm install -g npm@latest \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite && \
    sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf && \
    printf '\n<Directory "/var/www/html/public">\n    AllowOverride All\n    Require all granted\n</Directory>\n' >> /etc/apache2/apache2.conf && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts

COPY package.json package-lock.json* ./
RUN npm ci || npm install

COPY . .

RUN composer dump-autoload -o && php artisan package:discover --ansi || true
RUN npm run build

RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 775 storage bootstrap/cache

EXPOSE 80
CMD ["apache2-foreground"]
