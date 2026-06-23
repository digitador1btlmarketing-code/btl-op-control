#!/bin/sh
set -e

# Clear any cached configurations to ensure runtime env vars are respected
echo "Clearing cached configurations..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear


# Run migrations to ensure schema is up-to-date at runtime
echo "Running database migrations..."
php artisan migrate --force

# Start the Laravel serve command
echo "Starting Laravel server..."
exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
