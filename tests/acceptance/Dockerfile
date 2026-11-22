ARG BASE=php:8.5.13-cli
FROM ${BASE}
ARG IMAGICK_REF=3.8.1
RUN apt-get update -qq && apt-get install -y --no-install-recommends \
    libmagickwand-dev libmagickcore-7.q16-10-extra libheif-plugin-libde265 \
    libzip-dev libonig-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libavif-dev \
    libfreetype6-dev libicu-dev unzip git curl ca-certificates \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-avif --with-freetype \
    && docker-php-ext-install -j4 gd exif mbstring zip intl
RUN if [ "$IMAGICK_REF" = 3.8.1 ]; then \
        curl -fsSL --retry 2 https://pecl.php.net/get/imagick-3.8.1.tgz -o /tmp/imagick.tgz; \
    else \
        curl -fsSL --retry 2 "https://github.com/Imagick/imagick/archive/${IMAGICK_REF}.tar.gz" -o /tmp/imagick.tgz; \
    fi \
    && mkdir /tmp/imagick-src && tar xzf /tmp/imagick.tgz --strip-components=1 -C /tmp/imagick-src \
    && cd /tmp/imagick-src && phpize && ./configure && make -j4 && make install \
    && docker-php-ext-enable imagick
COPY --from=composer:2.10.2 /usr/bin/composer /usr/local/bin/composer
