FROM php:8.4-cli

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ARG ORACLE_IC_VERSION=23.9.0.25.07
ARG ORACLE_IC_DIR=instantclient_23_9

WORKDIR /app

# Oracle Instant Client is required to compile and run the OCI8 extension.
RUN apt-get update \
    && (apt-get install -y --no-install-recommends libaio1t64 || apt-get install -y --no-install-recommends libaio1) \
    && apt-get install -y --no-install-recommends curl unzip ${PHPIZE_DEPS} \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /opt/oracle \
    && curl -fsSL "https://download.oracle.com/otn_software/linux/instantclient/2390000/instantclient-basiclite-linux.x64-${ORACLE_IC_VERSION}.zip" -o /tmp/instantclient-basic.zip \
    && curl -fsSL "https://download.oracle.com/otn_software/linux/instantclient/2390000/instantclient-sdk-linux.x64-${ORACLE_IC_VERSION}.zip" -o /tmp/instantclient-sdk.zip \
    && unzip -q /tmp/instantclient-basic.zip -d /opt/oracle \
    && unzip -qo /tmp/instantclient-sdk.zip -d /opt/oracle \
    && ln -s "/opt/oracle/${ORACLE_IC_DIR}" /opt/oracle/instantclient \
    && echo "/opt/oracle/instantclient" > /etc/ld.so.conf.d/oracle-instantclient.conf \
    && ldconfig \
    && printf "instantclient,/opt/oracle/instantclient\n" | pecl install oci8 \
    && docker-php-ext-enable oci8 \
    && rm -f /tmp/instantclient-basic.zip /tmp/instantclient-sdk.zip

# Compile all PHP extensions before removing the build toolchain.
RUN docker-php-ext-install opcache \
    && apt-get purge -y --auto-remove curl unzip ${PHPIZE_DEPS} \
    && rm -rf /var/lib/apt/lists/*

EXPOSE 8000

CMD ["sh", "-lc", "composer install --no-interaction --prefer-dist && php -S 0.0.0.0:8000 -t public public/index.php"]
