FROM php:8.2-apache

# Enable Apache rewrite + install curl for Telegram/Discord notifications
RUN a2enmod rewrite \
    && apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

# Copy app files into the web root
COPY . /var/www/html/

# Make sure PHP can write the log + rate-limit files
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Apache listens on 80 by default; Render requires binding to $PORT
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf \
    && sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf

EXPOSE 10000

CMD ["apache2-foreground"]
