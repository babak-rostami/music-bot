FROM php:8.3-fpm

# --- System packages ---
# libwebp-dev / libjpeg / libpng / libfreetype  => برای GD با پشتیبانی WebP
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    curl \
    zip \
    ffmpeg \
    libpq-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    && rm -rf /var/lib/apt/lists/*

# --- PHP extensions ---
# gd  : پردازش تصویر (با WebP)
# ftp : درایور FTP فلای‌سیستم لاراول
# exif: خواندن اطلاعات EXIF (چرخش خودکار عکس‌های موبایل)
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
    pdo_pgsql \
    pgsql \
    gd \
    ftp \
    exif \
    zip

# --- PECL extensions ---
RUN pecl install redis \
    && docker-php-ext-enable redis

# --- (اختیاری) Imagick: کیفیت بهتر + فرمت‌های بیشتر ---
# اگر لازم نداری این بلوک را کامنت کن تا بیلد سریع‌تر شود.
RUN apt-get update && apt-get install -y --no-install-recommends libmagickwand-dev \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && rm -rf /var/lib/apt/lists/*

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
