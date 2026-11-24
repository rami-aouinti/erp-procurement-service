FROM php:8.4-cli

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

RUN docker-php-ext-install opcache

EXPOSE 8000

CMD ["sh", "-lc", "composer install --no-interaction --prefer-dist && php -S 0.0.0.0:8000 -t public public/index.php"]
