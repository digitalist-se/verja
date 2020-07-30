FROM php:7.3-cli-alpine

RUN apk add zlib-dev
RUN apk add icu-dev && apk add libzip-dev g++
RUN docker-php-ext-install zip
RUN docker-php-ext-install json
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer 

ADD . /app/.
RUN cd /app && composer install

ENV CONFIG='/app/config.yml'
ENV CPE='/app/cpe.txt'

COPY entrypoint.sh /entrypoint.sh

# Executes `entrypoint.sh` when the Docker container starts up 
ENTRYPOINT ["/entrypoint.sh"]
