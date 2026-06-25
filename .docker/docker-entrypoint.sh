#!/bin/sh
set -e

echo "=== Pinjaman (Loan) Service Startup ==="

# Wait for MySQL database to be ready
echo "[1/4] Waiting for database connection..."
until php -r "
try {
    \$pdo = new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD'),
        [PDO::ATTR_TIMEOUT => 3]
    );
    exit(0);
} catch (Exception \$e) {
    exit(1);
}
" 2>/dev/null; do
    echo "  Database not ready, retrying in 3 seconds..."
    sleep 3
done
echo "  Database connection OK!"

# Run migrations
echo "[2/4] Running migrations..."
php artisan migrate --force
echo "  Migrations complete!"

# Run seeders (creates roles: warga, staf, admin)
echo "[3/4] Running seeders..."
php artisan db:seed --force
echo "  Seeders complete!"

# Clear caches to ensure fresh boot
echo "[4/4] Clearing application caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
echo "  Caches cleared!"

echo "=== Starting Apache Server ==="
exec apache2-foreground
