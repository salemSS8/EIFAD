#!/bin/bash

# Exit on error
set -e

echo "🚀 Starting Deployment Script..."

# 1. Run Database Migrations
echo "📦 Running Migrations..."
php artisan migrate --force

# 2. Cache Configuration & Routes (Optimization)
echo "🔥 Optimizing Application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 3. Start Background Processes (Queue & Scheduler)
# We use '&' to run them in the background so they don't block the web server.
echo "👷 Starting Queue Worker..."
php artisan queue:work --tries=3 --timeout=90 &

echo "⏰ Starting Scheduler..."
php artisan schedule:work &

# 4. Start Web Server
# Using built-in server for compatibility. 
# Railway provides the PORT environment variable.
echo "🌐 Starting Web Server on port ${PORT:-8080}..."
php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
