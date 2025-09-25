# Usa uma imagem base oficial do PHP 8.1 com Apache
FROM php:8.1-apache

# --- 1. Instalação de Dependências do Sistema ---
# Instala o essencial: git, unzip, zip, poppler-utils e extensões do PHP
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        poppler-utils \
    && docker-php-ext-install pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# --- 2. Instalação do Composer ---
# Instala o Composer globalmente
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# --- 3. Configuração do Apache ---
# Habilita o mod_rewrite para os arquivos .htaccess
RUN a2enmod rewrite

# Define o DocumentRoot do Apache para a pasta /public da nossa aplicação
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf
RUN sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}/!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# --- 4. Define o Diretório de Trabalho ---
WORKDIR /var/www/html

# --- 5. Execução ---
# Expõe a porta 80
EXPOSE 80

# O comando padrão da imagem (apache2-foreground) já é o que queremos.
# Não precisamos definir CMD ou ENTRYPOINT.