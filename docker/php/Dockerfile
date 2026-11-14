# Le Cercle — image PHP/Apache multi-étapes (dev, vendor, ci, lint, test, jstest)

# Image de base : PHP/Apache + PDO MySQL (mbstring est déjà incluse)
FROM php:8.3-apache AS base
RUN docker-php-ext-install pdo_mysql
# Racine web : public/ ; api/ servi sous /api (voir apache-site.conf)
COPY docker/php/apache-site.conf /etc/apache2/sites-available/000-default.conf

# Développement : Composer, git/unzip et pcov (installé mais chargé seulement pour la couverture)
FROM base AS dev
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN --mount=type=cache,target=/var/cache/apt,sharing=locked \
    --mount=type=cache,target=/var/lib/apt,sharing=locked \
    apt-get update && apt-get install -y --no-install-recommends git unzip \
    && pecl install pcov

# Dépendances Composer
FROM dev AS vendor
WORKDIR /app
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    composer install --no-interaction --prefer-dist

# Sources + dépendances, base des cibles CI
FROM vendor AS ci
COPY . .

FROM ci AS lint
RUN composer lint

FROM ci AS test
RUN composer test && composer test:slow

# Tests JS conservés jusqu'au portage (Plan 5)
FROM node:22-alpine AS jstest
WORKDIR /app
COPY tests/ tests/
COPY public/js/ public/js/
RUN node tests/front/unit/run.mjs

# Contrôle du front : syntaxe de chaque script et cohérence avec index.html
FROM node:22-alpine AS jscheck
WORKDIR /app
COPY public/ public/
COPY tests/front/check-scripts.mjs tests/front/
RUN find public/js -name '*.js' -print0 | xargs -0 -n1 node --check \
    && node tests/front/check-scripts.mjs

# Test de fumée navigateur (Playwright) : dépendance npm limitée à cette image
FROM mcr.microsoft.com/playwright:v1.49.1-noble AS smoke
WORKDIR /smoke
RUN npm install --no-save --no-package-lock --ignore-scripts playwright-core@1.49.1
COPY tests/front/smoke.mjs ./
CMD ["node", "smoke.mjs"]
