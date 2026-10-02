# Production image. The fleet is pinned to PHP 7.4 until the 2027 platform upgrade.
FROM php:7.4.33-cli-alpine
WORKDIR /srv/app
COPY . .
CMD ["php", "tests/run.php"]
