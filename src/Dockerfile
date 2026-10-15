FROM php:8.3-cli-alpine
 
RUN docker-php-ext-install pdo_mysql
 
WORKDIR /opt/app
 
COPY --chown=1001:0 index.php /opt/app/index.php
 
RUN chmod -R g=u /opt/app
 
EXPOSE 8080
 
USER 1001
 
CMD ["php", "-S", "0.0.0.0:8080", "-t", "/opt/app"]
