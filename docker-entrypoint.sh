#!/bin/sh
set -e

# Print environment variables for debugging connection (excluding password)
echo "Runtime environment variables check:"
echo "DB_CONNECTION=$DB_CONNECTION"
echo "DB_HOST=$DB_HOST"
echo "DB_PORT=$DB_PORT"
echo "DB_DATABASE=$DB_DATABASE"
echo "DB_USERNAME=$DB_USERNAME"

# Clear any cached configurations to ensure runtime env vars are respected
echo "Clearing cached configurations..."
php artisan optimize:clear
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear


# Run migrations to ensure schema is up-to-date at runtime
echo "Running database migrations..."
php artisan migrate --force

# Create public storage symlink if it does not exist
echo "Linking public storage..."
php artisan storage:link || true

# Start the Laravel serve command
echo "Starting Laravel server..."
exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
