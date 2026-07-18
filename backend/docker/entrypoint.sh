#!/bin/sh
set -e

# Configure Docker socket access if it exists
if [ -S /var/run/docker.sock ]; then
    DOCKER_GID=$(stat -c '%g' /var/run/docker.sock)
    
    # Find group name for this GID or create it
    GROUP_NAME=$(awk -F: -v gid="$DOCKER_GID" '$3 == gid {print $1}' /etc/group)
    if [ -z "$GROUP_NAME" ]; then
        GROUP_NAME="docker-host"
        addgroup -g "$DOCKER_GID" "$GROUP_NAME"
    fi
    
    # Add www-data to the group so Laravel can communicate with Docker
    addgroup www-data "$GROUP_NAME"
fi

# Ensure storage and bootstrap/cache directories are writable
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# If .env doesn't exist, copy from .env.example
if [ ! -f ".env" ]; then
    echo "Creating .env file from .env.example..."
    cp .env.example .env
fi

# If vendor directory is missing in the mounted host volume, restore it from the built image
if [ ! -d "vendor" ] && [ -d "/var/web/vendor" ]; then
    echo "Local vendor directory not found. Restoring from Docker image..."
    cp -r /var/web/vendor vendor
fi

# Execute the main container command
exec "$@"
