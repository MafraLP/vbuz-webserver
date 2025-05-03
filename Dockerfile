FROM php:8.2-fpm

# Definir diretório de trabalho
WORKDIR /var/www

# Instalar dependências
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libpq-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev

# Instalar extensões PHP
RUN docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip

# Instalar o Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar usuário não-root
RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www

# Copiar configuração PHP personalizada
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Copiar aplicação
COPY . /var/www

# Mudar propriedade dos arquivos
RUN chown -R www:www /var/www

# Mudar para usuário não-root
USER www

# Expor porta
EXPOSE 9000

CMD ["php-fpm"]
