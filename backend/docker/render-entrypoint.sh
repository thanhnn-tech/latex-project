#!/bin/sh
set -e

# Ensure storage and bootstrap/cache directories are writable
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# If .env doesn't exist, copy from .env.example
if [ ! -f ".env" ]; then
    echo "Creating .env file from .env.example..."
    cp .env.example .env
fi

# Render sets PORT dynamically; bake it into the nginx server block. Fall back
# to the image default if it's somehow unset/empty, since an empty ${PORT}
# produces an invalid "listen ;" directive that crash-loops nginx.
: "${PORT:=10000}"
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/conf.d/default.conf
nginx -t

# Execute the main container command
exec "$@"
