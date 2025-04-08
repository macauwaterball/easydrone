FROM php:8.1-fpm

# 安裝依賴
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql zip

# 設置工作目錄
WORKDIR /var/www/html

# 設置權限
RUN chown -R www-data:www-data /var/www/html

# 暴露端口
EXPOSE 9000

# 啟動 PHP-FPM
CMD ["php-fpm"]