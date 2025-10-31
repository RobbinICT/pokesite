FROM php:8.2-fpm

# Install system dependencies including bash and useful terminal tools
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    default-mysql-client \
    libyaml-dev \
    libicu-dev \
    curl \
    bash \
    bash-completion \
    vim \
    nano \
    less \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mbstring zip pdo pdo_mysql soap intl opcache \
    && pecl install yaml \
    && docker-php-ext-enable yaml \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js and npm
RUN curl -sL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Symfony CLI
RUN curl -sS https://get.symfony.com/cli/installer | bash && \
    mv /root/.symfony*/bin/symfony /usr/local/bin/symfony

# Configure bash with history and useful features
RUN echo 'export HISTFILESIZE=10000' >> /root/.bashrc && \
    echo 'export HISTSIZE=10000' >> /root/.bashrc && \
    echo 'export HISTCONTROL=ignoredups:erasedups' >> /root/.bashrc && \
    echo 'shopt -s histappend' >> /root/.bashrc && \
    echo 'alias ll="ls -lah"' >> /root/.bashrc && \
    echo 'alias sf="php bin/console"' >> /root/.bashrc && \
    echo 'export TERM=xterm' >> /root/.bashrc

# Set bash as the default shell
ENV SHELL=/bin/bash
RUN ln -sf /bin/bash /bin/sh

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Set permissions for entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port 9000 and start php-fpm server
EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
