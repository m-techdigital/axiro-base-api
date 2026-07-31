#!/bin/sh

echo "Starting Laravel..."

php artisan config:clear
php artisan cache:clear

php artisan migrate --force

php artisan db:seed --force

php artisan storage:link || true

php artisan serve --host=0.0.0.0 --port=$PORT
