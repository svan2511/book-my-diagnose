# Backend-only deploy: Laravel API. Context = backend/ (Render Root Directory = backend).
# Postgres-ready for Render. Frontend is NOT built here — pre-build locally
# into public/app first if you want Laravel to serve the SPA, or host it separately.
FROM php:8.4-apache

# System deps + PHP extensions (Postgres, zip, standard Laravel set).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev libonig-dev libicu-dev unzip git \
    && docker-php-ext-install pdo_pgsql intl bcmath mbstring zip exif pcntl opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
# Serve Laravel's public dir; allow .htaccess rewrites (SPA fallback + API).
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

# PHP limits match the app's 10MB report cap.
RUN printf 'upload_max_filesize=10M\npost_max_size=12M\nmemory_limit=256M\nmax_execution_time=120\n' > /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/html

# Composer deps first (better layer caching).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts \
    && composer dump-autoload --optimize --no-scripts

# App code (.env is dockerignored — config comes from environment).
# NOTE: if public/app (React build) exists locally, it ships too and
# routes/web.php will serve it same-origin.
COPY . ./
# Drop the dev-discovered manifest (references dev-only providers like Pail),
# then rediscover for the production (--no-dev) vendor set.
RUN rm -f bootstrap/cache/*.php && php artisan package:discover --ansi

# Entrypoint lives at backend/entrypoint.sh (copied to backend root).
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Render injects $PORT at runtime (entrypoint rewires Apache to it).
EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
