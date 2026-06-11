# Linux Deployment Guide

This guide prepares a Linux server to run DocuMind in production with PDF, DOCX, XLSX, and CSV extraction, scanned-PDF OCR, queues, Gemini embeddings, PostgreSQL, and pgvector.

The commands below target Ubuntu/Debian-style servers. Adjust package names if you deploy on another distribution.

## 1. Install System Packages

Update packages:

```bash
sudo apt update
sudo apt upgrade -y
```

Install base tools:

```bash
sudo apt install -y git unzip curl ca-certificates software-properties-common
```

Install PHP and common Laravel extensions. The `php-zip`, `php-xml`, and `php-mbstring` packages are required for DOCX extraction with PhpOffice/PHPWord and XLSX/CSV extraction with PhpOffice/PhpSpreadsheet. The `php-gd` package is commonly required by PhpSpreadsheet:

```bash
sudo apt install -y php php-cli php-fpm php-pgsql php-mbstring php-xml php-curl php-zip php-bcmath php-intl php-gd
```

Install Composer:

```bash
sudo apt install -y composer
composer --version
```

Install Node.js and npm:

```bash
sudo apt install -y nodejs npm
node --version
npm --version
```

Install Poppler and Tesseract OCR for PDF extraction and scanned-PDF fallback. XLSX and CSV extraction does not need a separate Linux binary:

```bash
sudo apt install -y poppler-utils tesseract-ocr tesseract-ocr-eng
pdftotext -v
pdftoppm -v
tesseract --version
```

Install PostgreSQL and supervisor:

```bash
sudo apt install -y postgresql postgresql-contrib supervisor
```

## 2. Install pgvector

If your distribution provides a pgvector package, install the package matching your PostgreSQL version. For example:

```bash
sudo apt install -y postgresql-16-pgvector
```

If that package is not available, install pgvector from source:

```bash
sudo apt install -y build-essential postgresql-server-dev-all
git clone https://github.com/pgvector/pgvector.git /tmp/pgvector
cd /tmp/pgvector
make
sudo make install
```

Create the database and enable the extension:

```bash
sudo -u postgres psql
```

```sql
CREATE DATABASE documind;
CREATE USER documind_user WITH ENCRYPTED PASSWORD 'change-this-password';
GRANT ALL PRIVILEGES ON DATABASE documind TO documind_user;
\c documind
CREATE EXTENSION IF NOT EXISTS vector;
\q
```

## 3. Deploy The Application

Clone or copy the project:

```bash
sudo mkdir -p /var/www/documind
sudo chown -R $USER:www-data /var/www/documind
git clone <your-repository-url> /var/www/documind
cd /var/www/documind
```

Install PHP dependencies:

```bash
composer install --no-dev --optimize-autoloader
```

This installs the Laravel dependencies, including PhpOffice/PHPWord for DOCX text extraction and PhpOffice/PhpSpreadsheet for XLSX and CSV text extraction.

Install and build frontend assets:

```bash
npm ci
npm run build
```

Create the environment file:

```bash
cp .env.example .env
php artisan key:generate
```

## 4. Configure `.env`

Use production-safe values:

```env
APP_NAME=DocuMind
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=documind
DB_USERNAME=documind_user
DB_PASSWORD=change-this-password

QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

PDFTOTEXT_PATH=/usr/bin/pdftotext
PDFTOPPM_PATH=/usr/bin/pdftoppm
OCR_ENABLED=true
OCR_LANGUAGE=eng
OCR_MIN_TEXT_CHARACTERS=20
OCR_MIN_TEXT_DENSITY_PER_PAGE=10
OCR_PDF_DPI=200
PDFTOPPM_TIMEOUT=300
TESSERACT_PATH=/usr/bin/tesseract
TESSERACT_TIMEOUT=120

EMBEDDING_PROVIDER=gemini
LLM_PROVIDER=gemini
GEMINI_API_KEY=your-gemini-api-key
GEMINI_EMBEDDING_MODEL=gemini-embedding-2
GEMINI_CHAT_MODEL=gemini-2.5-flash
EMBEDDING_DIMENSIONS=1536

RAG_TOP_K=6
RAG_SUMMARY_TOP_K=12
RAG_MAX_CONTEXT_CHARS=24000
RAG_RETRIEVAL_MAX_DISTANCE=
RAG_MESSAGE_RATE_LIMIT_PER_MINUTE=20
```

Confirm binary paths:

```bash
which pdftotext
which pdftoppm
which tesseract
```

If the paths differ, update `.env` with the real paths.

For Windows/XAMPP development, enable the PHP `zip`, `xml`, and `mbstring` extensions in `php.ini` if they are disabled, then restart Apache and the queue worker. Keep explicit `.exe` paths for Poppler and Tesseract in `.env` when they are not on the Windows `PATH`.

## 5. Permissions

Laravel needs write access to `storage` and `bootstrap/cache`:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

Private uploaded documents are stored under Laravel's local storage disk, not in the public web root.

## 6. Run Migrations And Optimize

Run migrations:

```bash
php artisan migrate --force
```

The migrations create the `document_chunks.embedding` pgvector column and the HNSW cosine index used by RAG retrieval.

Cache production configuration:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Whenever `.env` changes, refresh the cache and restart workers:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan queue:restart
```

## 7. Configure Queue Worker

Create a supervisor config:

```bash
sudo nano /etc/supervisor/conf.d/documind-worker.conf
```

Use:

```ini
[program:documind-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/documind/artisan queue:work --sleep=3 --tries=1 --timeout=0
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/documind/storage/logs/worker.log
stopwaitsecs=3600
```

Start the worker:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start documind-worker:*
sudo supervisorctl status
```

OCR can be slow for large scanned PDFs, so the worker uses `--timeout=0`. If you use a different queue backend, keep its visibility timeout longer than the largest expected OCR job.

### Live Status Updates

DocuMind uses lightweight browser polling for live document processing status updates.

No additional services such as WebSockets, Laravel Reverb, Redis Pub/Sub, or Socket servers are required for this feature.

The polling system automatically pauses when the browser tab is hidden and stops when a document reaches `Ready` or `Failed` status.

```text
Queue job updates document status in database
   |
   v
Status API endpoint returns latest status
   |
   v
Browser polling updates status badges and timelines
   |
   v
Polling stops when document is Ready or Failed
```

## 8. Configure Nginx

Create a site config:

```bash
sudo nano /etc/nginx/sites-available/documind
```

Example:

```nginx
server {
    listen 80;
    server_name your-domain.example;
    root /var/www/documind/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Your PHP-FPM socket path may include the PHP version, for example `/var/run/php/php8.3-fpm.sock`. Check it with:

```bash
ls /var/run/php/
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/documind /etc/nginx/sites-enabled/documind
sudo nginx -t
sudo systemctl reload nginx
```

Add HTTPS with your preferred certificate tool, such as Certbot.

## 9. Verify The Server

Check Laravel:

```bash
php artisan about
php artisan migrate:status
```

Check OCR binaries:

```bash
/usr/bin/pdftotext -v
/usr/bin/pdftoppm -v
/usr/bin/tesseract --version
```

Check Composer autoload can see Tesseract:

```bash
php artisan tinker --execute="echo class_exists('thiagoalessio\\TesseractOCR\\TesseractOCR') ? 'yes' : 'no';"
```

Expected output:

```text
yes
```

Check Composer autoload can see PHPWord:

```bash
php artisan tinker --execute="echo class_exists('PhpOffice\\PhpWord\\IOFactory') ? 'yes' : 'no';"
```

Expected output:

```text
yes
```

Check Composer autoload can see PhpSpreadsheet:

```bash
php artisan tinker --execute="echo class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory') ? 'yes' : 'no';"
```

Expected output:

```text
yes
```

Check queue status:

```bash
sudo supervisorctl status
tail -f storage/logs/worker.log
```

## 10. Test The Application Flow

1. Register a user.
2. Verify the user's email.
3. Upload a text-based PDF and confirm it becomes `Ready` and chunks are created.
4. Upload a scanned PDF and confirm OCR processing creates chunks.
5. Upload a DOCX and confirm text extraction creates chunks.
6. Upload an XLSX and confirm text extraction creates chunks.
7. Upload a CSV and confirm text extraction creates chunks.
8. Create a chat conversation scoped to the document.
9. Ask a question that can be answered from the document.
10. Confirm the answer includes source citations.

## Troubleshooting

If OCR says `tesseract` was not found, set `TESSERACT_PATH=/usr/bin/tesseract`, run `php artisan optimize:clear`, then restart the queue worker.

If Poppler fails, confirm both `pdftotext` and `pdftoppm` are installed with `poppler-utils`.

If DOCX extraction fails with missing class or zip/xml errors, run `composer install --no-dev --optimize-autoloader`, confirm `php-zip`, `php-xml`, and `php-mbstring` are installed, then restart PHP-FPM and queue workers.

If XLSX or CSV extraction fails, confirm PhpSpreadsheet is installed with Composer and that `php-zip`, `php-xml`, `php-mbstring`, and `php-gd` are enabled.

If CSV uploads fail validation, check the detected MIME type. Browsers and servers may report CSV files as `text/plain`, `text/csv`, `application/csv`, or `application/vnd.ms-excel`.

If migrations fail on `vector`, confirm pgvector is installed and the database has:

```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

If scanned PDFs process slowly, that is expected. Increase server CPU, reduce `OCR_PDF_DPI`, or run more queue workers only if the server has enough CPU and memory.
