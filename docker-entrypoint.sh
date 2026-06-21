#!/bin/sh
set -e

# If DB_DATABASE is set, ensure the directory and file exist
if [ -n "$DB_DATABASE" ]; then
    echo "Using database at: $DB_DATABASE"
    mkdir -p "$(dirname "$DB_DATABASE")"
    if [ ! -f "$DB_DATABASE" ]; then
        echo "Database file does not exist. Creating..."
        touch "$DB_DATABASE"
    fi
else
    echo "DB_DATABASE is not set. Defaulting to database/database.sqlite"
    # Ensure default sqlite file exists
    mkdir -p database
    touch database/database.sqlite
fi

# Run migrations to ensure schema is up-to-date at runtime
echo "Running database migrations..."
php artisan migrate --force

# Start the Laravel serve command
echo "Starting Laravel server..."
exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
