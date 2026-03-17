FROM php:8.2-apache

# Instala dependências do sistema
RUN apt-get update && apt-get install -y \
    wget \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libcurl4-openssl-dev \
    libssl-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Instala extensões PHP necessárias para o vTiger 8.4
RUN docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install \
        mysqli \
        pdo \
        pdo_mysql \
        gd \
        curl \
        mbstring \
        zip \
        xml \
        soap \
        intl \
        bcmath \
        exif \
        opcache

# Habilita mod_rewrite do Apache
RUN a2enmod rewrite

# Configura Apache para permitir .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Baixa e extrai o vTiger CRM 8.4.0
WORKDIR /tmp
RUN wget -q --content-disposition \
    "https://sourceforge.net/projects/vtigercrm/files/vtiger%20CRM%208.4.0/Core%20Product/vtigercrm8.4.0.tar.gz/download" \
    -O vtigercrm8.4.0.tar.gz \
    && tar -xzf vtigercrm8.4.0.tar.gz \
    && cp -r vtigercrm/. /var/www/html/ \
    && rm -rf /tmp/vtigercrm* \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Instala módulos do pkg/ nos diretórios corretos (o que o instalador web faria)
RUN set -e; \
    PKG=/var/www/html/pkg/vtiger/modules; \
    for moddir in "$PKG"/*/; do \
        mod=$(basename "$moddir"); \
        # Módulo principal: pkg/Mod/modules/Mod/ -> modules/Mod/
        if [ -d "$moddir/modules/$mod" ]; then \
            cp -r "$moddir/modules/$mod" /var/www/html/modules/; \
        fi; \
        # Módulo Settings: pkg/Mod/settings/ -> modules/Settings/Mod/
        if [ -d "$moddir/settings" ]; then \
            cp -r "$moddir/settings" /var/www/html/modules/Settings/$mod; \
        fi; \
        # Layouts: pkg/Mod/layouts/ -> layouts/
        if [ -d "$moddir/layouts" ]; then \
            cp -r "$moddir/layouts/." /var/www/html/layouts/; \
        fi; \
        # Linguagem en_us: pkg/Mod/languages/en_us/ -> languages/en_us/
        if [ -d "$moddir/languages/en_us" ]; then \
            cp -r "$moddir/languages/en_us/." /var/www/html/languages/en_us/; \
        fi; \
    done; \
    # Projects tem estrutura especial: Project, ProjectMilestone, ProjectTask
    for sub in Project ProjectMilestone ProjectTask; do \
        if [ -d "$PKG/../Projects/$sub" ]; then \
            cp -r "$PKG/../Projects/$sub" /var/www/html/modules/; \
        fi; \
    done; \
    chown -R www-data:www-data /var/www/html/modules /var/www/html/layouts /var/www/html/languages

# Cria php.ini a partir do template de produção com ajustes para o vTiger
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini \
    && sed -i 's/memory_limit = 128M/memory_limit = 256M/' /usr/local/etc/php/php.ini \
    && sed -i 's/display_errors = Off/display_errors = On/' /usr/local/etc/php/php.ini \
    && sed -i 's/;date.timezone =/date.timezone = America\/Recife/' /usr/local/etc/php/php.ini \
    && sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 32M/' /usr/local/etc/php/php.ini \
    && sed -i 's/post_max_size = 8M/post_max_size = 32M/' /usr/local/etc/php/php.ini \
    && sed -i 's/max_execution_time = 30/max_execution_time = 300/' /usr/local/etc/php/php.ini

EXPOSE 80
