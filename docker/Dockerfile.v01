FROM php:7.4.33-apache

# Copy virtual host into container
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Enable rewrite mode
RUN a2enmod rewrite

# Install necessary packages
RUN apt-get update

RUN apt-get install \
    wget \
    git \
    unzip \
    libzip-dev \
    openssl \
    libssl-dev \
    -y --no-install-recommends

# Install PHP Extensions
RUN docker-php-ext-install -j "$(nproc)" \
    pdo \
    pdo_mysql \
    mysqli \
    zip

RUN docker-php-ext-install zip pdo_mysql

# mongoDb driver
RUN pecl install mongodb
RUN echo "extension=mongodb.so" >> /usr/local/etc/php/conf.d/mongodb.ini

# Xdebug
RUN pecl install -o -f xdebug-3.1.5
RUN docker-php-ext-enable xdebug

# # Copy composer installable
# #COPY ./install-composer.sh ./

# Copy php.ini
COPY ./php.ini /usr/local/etc/php/

# Cleanup packages and install composer
RUN apt-get purge -y g++ \
    && apt-get autoremove -y \
    && rm -r /var/lib/apt/lists/* \
    && rm -rf /tmp/*

# Change the current working directory
WORKDIR /var/www

# Change the owner of the container document root
RUN chown -R www-data:www-data /var/www

# Start Apache in foreground
CMD ["apache2-foreground"]

