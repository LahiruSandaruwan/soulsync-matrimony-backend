#!/bin/bash

# SoulSync Matrimony WebSocket Server Startup Script
# This script starts the Laravel WebSockets server for real-time communication

echo "🚀 Starting SoulSync Matrimony WebSocket Server..."

# Check if we're in the correct directory
if [ ! -f "artisan" ]; then
    echo "❌ Error: Please run this script from the Laravel project root directory"
    exit 1
fi

# Check if .env file exists
if [ ! -f ".env" ]; then
    echo "❌ Error: .env file not found. Please create one from .env.example"
    exit 1
fi

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo "📦 Installing dependencies..."
    composer install
fi

# Check if database is configured
echo "🔍 Checking database connection..."
php artisan migrate:status > /dev/null 2>&1
if [ $? -ne 0 ]; then
    echo "❌ Error: Database connection failed. Please check your .env configuration"
    exit 1
fi

# Run migrations if needed
echo "🗄️  Running database migrations..."
php artisan migrate --force

# Clear caches
echo "🧹 Clearing application caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Check if WebSocket port is available
WEBSOCKET_PORT=$(grep "WEBSOCKET_PORT" .env | cut -d '=' -f2 | tr -d ' ')
if [ -z "$WEBSOCKET_PORT" ]; then
    WEBSOCKET_PORT=6001
fi

echo "🔌 Checking if port $WEBSOCKET_PORT is available..."
if lsof -Pi :$WEBSOCKET_PORT -sTCP:LISTEN -t >/dev/null ; then
    echo "⚠️  Warning: Port $WEBSOCKET_PORT is already in use"
    echo "   You may need to stop the existing WebSocket server first"
    read -p "   Continue anyway? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Start the WebSocket server
echo "🌟 Starting Laravel WebSockets server on port $WEBSOCKET_PORT..."
echo "📊 WebSocket Dashboard: http://localhost:$WEBSOCKET_PORT/laravel-websockets"
echo "🔗 WebSocket URL: ws://localhost:$WEBSOCKET_PORT"
echo ""
echo "Press Ctrl+C to stop the server"
echo ""

# Start the WebSocket server
php artisan websockets:serve --host=0.0.0.0 --port=$WEBSOCKET_PORT 