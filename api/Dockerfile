FROM php:8.4-cli

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    make \
    gcc \
    libc-dev \
    libpq-dev \
    librabbitmq-dev \
    libzip-dev \
    zlib1g-dev \
    && docker-php-ext-install pdo_pgsql zip \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . /var/www/html

RUN composer install --no-interaction --no-progress --prefer-dist --no-plugins --no-scripts \
    && php bin/console cache:clear || true

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
