# ── Stage 1: Build Angular ────────────────────────────────────────────────────
FROM node:20-alpine AS frontend

WORKDIR /build
COPY frontend/package*.json ./
RUN npm ci --silent

COPY frontend/ ./
RUN npx ng build --configuration production

# ── Stage 2: PHP runtime ──────────────────────────────────────────────────────
FROM php:8.2-cli-alpine

# PHP extensions required by the app
RUN docker-php-ext-install pdo pdo_mysql

# Composer (copy binary from official image — no separate install needed)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP dependencies (production only, no dev tools)
COPY backend/composer.json backend/composer.lock ./backend/
RUN cd backend \
 && composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy full backend source
COPY backend/ ./backend/

# Copy Angular build output into PHP's public folder
# Angular 17+ outputs browser files to dist/frontend/browser/
COPY --from=frontend /build/dist/frontend/browser/ ./backend/public/

# Railway sets $PORT dynamically at runtime (default 8080 for local Docker)
EXPOSE 8080

# Run migrations (idempotent: errors 1050/1060/1061 are silently ignored),
# then start the PHP dev server with the SPA router.
CMD php /app/backend/scripts/migrate.php --demo \
 && php -S 0.0.0.0:${PORT:-8080} -t /app/backend/public /app/backend/public/router.php
