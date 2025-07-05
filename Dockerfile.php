FROM php:8.1-fpm

# 安裝依賴
# 將 apt-get update 和 install 合併到一個 RUN 命令中
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/* # 清理 apt 緩存以減小映像大小

# 設置工作目錄
WORKDIR /var/www/html

# 設置權限
RUN chown -R www-data:www-data /var/www/html

# 暴露端口
EXPOSE 9000

# 啟動 PHP-FPM
CMD ["php-fpm"]