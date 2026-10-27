# Development image. Production will get its own stage later.
# FrankenPHP is the PHP app server (built on Caddy); the same image also runs
# queue workers and the scheduler with a different command.
FROM dunglas/frankenphp:1-php8.5-trixie

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && install-php-extensions pdo_mysql zip intl pcntl bcmath redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

EXPOSE 8000
CMD ["frankenphp", "php-server", "--listen", ":8000", "--root", "public/"]
