# Installation Guide

This guide provides detailed instructions for setting up the Inventory Management System.

## Prerequisites

### Required Software

- **PHP 8.2+** with extensions:
  - BCMath
  - Ctype
  - Fileinfo
  - JSON
  - Mbstring
  - OpenSSL
  - PDO
  - Tokenizer
  - XML
  - GD or Imagick (for image processing)

- **Composer** (latest version)
- **Node.js 18+** with npm
- **Git**

### Database Options

- SQLite (default, no setup required)
- MySQL 8.0+
- PostgreSQL 14+

## Step-by-Step Installation

### 1. Clone the Repository

```bash
git clone https://github.com/joefreire/inventory.git
cd inventory
```

### 2. Install PHP Dependencies

```bash
composer install
```

For production:
```bash
composer install --no-dev --optimize-autoloader
```

### 3. Install Node.js Dependencies

```bash
npm install
```

### 4. Environment Configuration

Copy the example environment file:
```bash
cp .env.example .env
```

Generate the application key:
```bash
php artisan key:generate
```

### 5. Database Setup

#### SQLite (Recommended for Development)

Create the database file:
```bash
touch database/database.sqlite
```

Configure `.env`:
```env
DB_CONNECTION=sqlite
```

#### MySQL

Configure `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventory
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Create the database:
```sql
CREATE DATABASE inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### PostgreSQL

Configure `.env`:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=inventory
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 6. Run Migrations

```bash
php artisan migrate
```

To seed with sample data:
```bash
php artisan migrate --seed
```

### 7. Storage Setup

Create the symbolic link for public storage:
```bash
php artisan storage:link
```

Ensure the storage directory is writable:
```bash
chmod -R 775 storage bootstrap/cache
```

### 8. Build Frontend Assets

For development:
```bash
npm run dev
```

For production:
```bash
npm run build
```

### 9. OpenAI Configuration (Optional)

To enable AI image recognition:

1. Get an API key from [OpenAI](https://platform.openai.com/api-keys)
2. Add to `.env`:
```env
OPENAI_API_KEY=sk-your-api-key-here
```

### 10. Start the Application

Development server:
```bash
php artisan serve
```

Visit `http://localhost:8000`

## Production Deployment

### Web Server Configuration

#### Nginx

```nginx
server {
    listen 80;
    server_name inventory.example.com;
    root /var/www/inventory/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### Apache

Ensure `mod_rewrite` is enabled. The `.htaccess` file in `public/` handles routing.

### Optimization

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer install --no-dev --optimize-autoloader
```

### Queue Worker (Optional)

If using queued jobs:
```bash
php artisan queue:work --daemon
```

## Troubleshooting

### Permission Issues

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Image Processing Errors

Ensure GD or Imagick is installed:
```bash
# Ubuntu/Debian
sudo apt-get install php8.2-gd

# Or for Imagick
sudo apt-get install php8.2-imagick
```

### Database Connection Errors

1. Verify database credentials in `.env`
2. Ensure database service is running
3. Check firewall rules for database port

### Storage Link Issues

If `storage:link` fails, manually create the link:
```bash
ln -s /path/to/inventory/storage/app/public /path/to/inventory/public/storage
```

## Updating

To update to the latest version:

```bash
git pull origin main
composer install
npm install
npm run build
php artisan migrate
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```
