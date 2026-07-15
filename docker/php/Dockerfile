FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libpq-dev \
    zip \
    ffmpeg

RUN docker-php-ext-install pdo_pgsql pgsql

RUN pecl install redis \
    && docker-php-ext-enable redis

# --- تنظیمات حجم آپلود و زمان اجرا برای PHP ---
RUN echo "upload_max_filesize = 300M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 300M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www

ENTRYPOINT ["entrypoint.sh"]