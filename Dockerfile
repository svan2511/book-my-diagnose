# Merged deploy: React SPA (built to public/app) + Laravel API in ONE image.
# Context: repository root (D:\lab). See render.yaml.

# ---------- Stage 1: build the React SPA ----------
FROM node:20-alpine AS frontend
WORKDIR /build/web-app
COPY web-app/package.json web-app/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY web-app/ ./
# Same-origin API — no CORS, no LAN IPs baked in.
ENV VITE_API_URL=/api/v1 \
    VITE_BASE=/app/ \
    VITE_OUTDIR=/app/spa
RUN npm run build

# ---------- Stage 2: PHP + Apache runtime ----------
# PHP 8.4 to match composer.lock (symfony 8.x needs >= 8.4.1).
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
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts \
    && composer dump-autoload --optimize --no-scripts

# App code (backend/.env is dockerignored — config comes from environment).
COPY backend/ ./
# Drop the dev-discovered manifest (references dev-only providers like Pail),
# then rediscover for the production (--no-dev) vendor set.
RUN rm -f bootstrap/cache/*.php && php artisan package:discover --ansi

# Built SPA into Laravel's public dir.
COPY --from=frontend /app/spa ./public/app

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Render injects $PORT at runtime (entrypoint rewires Apache to it).
EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
