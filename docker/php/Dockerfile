FROM php:8.3-fpm-alpine

# 设置时区
ENV TZ=Asia/Shanghai

# 安装系统依赖
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    libxml2-dev \
    icu-dev \
    oniguruma-dev \
    mysql-client \
    nodejs \
    npm \
    tzdata \
    $PHPIZE_DEPS

# 配置时区
RUN cp /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# 安装PHP扩展
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    mysqli \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    xml \
    intl \
    opcache \
    sockets

# 安装Redis扩展
RUN pecl install redis && docker-php-ext-enable redis

# 安装Xdebug扩展（仅开发环境）
RUN pecl install xdebug && docker-php-ext-enable xdebug

# 配置OPcache
RUN echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.max_accelerated_files=4000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.revalidate_freq=60" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

# 安装Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# 设置工作目录
WORKDIR /var/www/html

# 设置权限
RUN chown -R www-data:www-data /var/www/html

# 切换到www-data用户
USER www-data

# 健康检查
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

EXPOSE 9000

CMD ["php-fpm"]
