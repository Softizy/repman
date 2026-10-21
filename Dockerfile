FROM php:7.4.33-fpm-alpine

# PHP2Shell (CVE-2026-109618/109619): no 7.4 release, only the php-src source backport
# (same patch as Softizy/infog-container-php). PHP_VERSION still reads 7.4.33.
COPY docker/php/patches/ /usr/src/php-patches/
RUN set -eux; \
    apk add --no-cache --virtual .php-rebuild-deps \
        $PHPIZE_DEPS argon2-dev coreutils curl-dev gnu-libiconv-dev libsodium-dev \
        libxml2-dev linux-headers oniguruma-dev openssl-dev patch readline-dev sqlite-dev zlib-dev; \
    # same iconv and hardening flags as the official image build
    rm -vf /usr/include/iconv.h; \
    ln -sfv /usr/include/gnu-libiconv/*.h /usr/include/; \
    export CFLAGS="$PHP_CFLAGS" CPPFLAGS="$PHP_CPPFLAGS" LDFLAGS="$PHP_LDFLAGS"; \
    docker-php-source extract; \
    cd /usr/src/php; \
    patch -p1 < /usr/src/php-patches/php-7.4-CVE-2026-109618-109619.patch; \
    tar -cJf /usr/src/php.tar.xz --exclude=.docker-extracted -C /usr/src php; \
    eval ./configure $(php-config --configure-options); \
    make -j"$(nproc)"; \
    make install; \
    cd /; \
    docker-php-source delete; \
    grep -q zend_new_array_singleton /usr/local/sbin/php-fpm; \
    apk del --no-network .php-rebuild-deps; \
    rm -rf /usr/src/php-patches

ARG TIMEZONE="UTC"

SHELL ["sh", "-eo", "pipefail", "-c"]

# install composer and extensions: pdo_pgsql, intl, zip
RUN apk update && \
    apk add --no-cache -q \
    bash \
    git \
    subversion \
    openssh-client

RUN curl -sSLf \
    -o /usr/local/bin/install-php-extensions \
    https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions && \
    chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions opcache pdo_pgsql intl zip @composer && \
    rm /usr/local/bin/install-php-extensions

# set timezone
RUN ln -snf /usr/share/zoneinfo/${TIMEZONE} /etc/localtime && \
    echo ${TIMEZONE} > /etc/timezone && \
    printf '[PHP]\ndate.timezone = "%s"\n', "$TIMEZONE" > \
    /usr/local/etc/php/conf.d/tzone.ini && "date"

# set memory limit
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory-limit.ini

# PHP2Shell defence in depth
RUN echo "session.upload_progress.enabled=0" > /usr/local/etc/php/conf.d/zz-php2shell.ini

# hide X-Powered-By in reponse header
RUN echo "expose_php=off" > /usr/local/etc/php/conf.d/expose.ini

# automatically add new host keys to the user known hosts
RUN printf "Host *\n    StrictHostKeyChecking no" > /etc/ssh/ssh_config

RUN mkdir /app
WORKDIR /app

COPY . .

ENV APP_ENV=prod

RUN composer install --optimize-autoloader --no-dev; composer clear-cache
