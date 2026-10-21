#!/bin/ash

COMMON_NAME="*.${APP_HOST}"
SUBJECT="/C=PL/ST=None/L=None/O=Repman/CN=$COMMON_NAME"

adduser -D -H -u 1000 -s /bin/bash -G www-data www-data

sed s/%%PHP_URL%%/${PHP_URL}/g < /nginx/nginx.conf > /etc/nginx/nginx.conf

echo "Starting nginx"
nginx
