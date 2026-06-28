FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    git unzip curl ca-certificates \
    libpng-dev libonig-dev libxml2-dev \
    libzip-dev libpq-dev \
    && update-ca-certificates \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql mbstring exif pcntl bcmath gd zip \
    && docker-php-ext-enable opcache \
    && rm -rf /var/lib/apt/lists/*

# OPcache — produção (validate_timestamps=0: não re-verifica arquivos, seguro pois container é recriado no deploy)
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.enable_cli=0'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.save_comments=1'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get update && apt-get install -y nodejs \
    && npm install -g npm@latest \
    && rm -rf /var/lib/apt/lists/*

# Apache: rewrite + deflate (gzip) + expires (cache de assets)
RUN a2enmod rewrite deflate expires headers && \
    sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf && \
    printf '\n<Directory "/var/www/html/public">\n    AllowOverride All\n    Require all granted\n</Directory>\n' >> /etc/apache2/apache2.conf && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Cache de longa duração para assets com hash (Vite gera nomes como app-abc123.js)
RUN printf '<IfModule mod_expires.c>\n\
    ExpiresActive On\n\
    ExpiresByType application/javascript "access plus 1 year"\n\
    ExpiresByType text/css "access plus 1 year"\n\
    ExpiresByType image/png "access plus 1 year"\n\
    ExpiresByType image/svg+xml "access plus 1 year"\n\
    ExpiresByType font/woff2 "access plus 1 year"\n\
</IfModule>\n\
<IfModule mod_headers.c>\n\
    <FilesMatch "\\.(js|css|woff2|png|svg)$">\n\
        Header set Cache-Control "public, max-age=31536000, immutable"\n\
    </FilesMatch>\n\
</IfModule>\n' > /etc/apache2/conf-available/cache-assets.conf && \
    a2enconf cache-assets

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts

COPY package.json package-lock.json* ./
RUN npm ci || npm install

COPY . .

RUN composer dump-autoload -o && php artisan package:discover --ansi || true
RUN npm run build

RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 775 storage bootstrap/cache

EXPOSE 80
CMD ["apache2-foreground"]
