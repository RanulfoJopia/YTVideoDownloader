FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git curl unzip zip \
    libzip-dev libpng-dev libonig-dev \
    ffmpeg python3 python3-pip \
    && docker-php-ext-install pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

RUN pip3 install --break-system-packages -U yt-dlp

# Install Deno (JS runtime yt-dlp needs to solve YouTube's n-challenge)
RUN curl -fsSL https://deno.land/install.sh | sh
ENV PATH="/root/.deno/bin:${PATH}"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --optimize-autoloader --no-dev

RUN mkdir -p storage/app/downloads storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 10000

CMD php artisan config:cache && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}