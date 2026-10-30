FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev \
 && docker-php-ext-install mysqli pdo_pgsql \
 && rm -rf /var/lib/apt/lists/*
COPY . /var/www/html/
# Render sends traffic to $PORT
CMD sed -i "s/Listen 80/Listen ${PORT:-10000}/" /etc/apache2/ports.conf \
 && sed -i "s/:80>/:${PORT:-10000}>/" /etc/apache2/sites-available/000-default.conf \
 && apache2-foreground
