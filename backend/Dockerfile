FROM php:8.3-cli-alpine

# Instala dependências do sistema
RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    oniguruma-dev \
    libpng-dev \
    libzip-dev \
    linux-headers \
    $PHPIZE_DEPS

# Instala extensões PHP necessárias
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    zip \
    bcmath \
    pcntl

# Instala Redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# Instala Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

# Copia arquivos de dependências primeiro (cache de layers)
COPY composer.json composer.lock ./

# Instala dependências PHP sem autoload (será regenerado depois)
RUN composer install --no-scripts --no-autoloader --no-interaction

# Copia o restante do código
COPY . .

# Gera autoload otimizado
RUN composer dump-autoload --optimize

# Ajusta permissões
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
