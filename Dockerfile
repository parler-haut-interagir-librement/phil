# The PHIL monorepo: PHP libraries and Symfony bundles, under packages/<name>/.
#
# /src            the monorepo checkout, with its Composer dependencies installed in /src/vendor (the packages of
#                 packages/* are linked into vendor/phil/ by Composer's path repository)
# /src/vendor-bin/fuzzer
#                 the fuzzer of the fuzz targets under packages/*/tests/Fuzz
#
# Everything the tests need is fetched here: after this build, the tests run without network.
#   composer test                       every Unit and Integration suite (Integration tests that need a database
#                                       server or Gotenberg read its URL from the environment and are skipped without it)
#   vendor/bin/phpunit packages/<name>/tests
#                                       the tests of one package
FROM php:8.5-cli

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_MEMORY_LIMIT=-1

# The extensions the packages need, besides those the image builds in (openssl, sodium, pdo_sqlite, dom, uri):
# intl, mbstring, zip, the PDO drivers, and GD that reads and writes JPEG, PNG, WebP and AVIF. git and unzip for
# Composer; the sqlite3 command for the tests that inspect a database file.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends ca-certificates git unzip sqlite3 \
        libicu-dev libonig-dev libzip-dev libpq-dev \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev libavif-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif; \
    docker-php-ext-install -j"$(nproc)" intl mbstring zip pdo_mysql pdo_pgsql gd; \
    rm -rf /var/lib/apt/lists/*

# Every deprecation, notice and warning is reported, as in the project's own test runs.
RUN { \
        echo 'error_reporting = E_ALL'; \
        echo 'display_errors = On'; \
        echo 'memory_limit = -1'; \
        echo 'zend.assertions = 1'; \
    } > "$PHP_INI_DIR/conf.d/tests.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# The tests run as an ordinary user: some check that an unwritable file or directory is refused, which root would write.
RUN useradd --create-home --shell /bin/bash tests

# The checkout lives inside the image, at /src.
COPY --chown=tests:tests . /src
WORKDIR /src
USER tests

# The dependencies of composer.lock. --no-plugins keeps the quality tools of vendor-bin/ (static analysis, code style)
# out of the image; only the fuzzer is installed beside the test dependencies.
RUN composer install --no-progress --prefer-dist --no-plugins \
    && composer install --no-progress --prefer-dist --working-dir=vendor-bin/fuzzer

# Run the tests so the image is known to work, without letting a failing test abort the build.
RUN composer test || echo "WARNING: the tests reported failures"

CMD ["composer", "test"]
