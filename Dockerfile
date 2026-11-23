FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
  git \
  unzip \
  zip \
  $PHPIZE_DEPS

RUN pecl install pcov \
  && docker-php-ext-enable pcov

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

CMD ["php-fpm"]
