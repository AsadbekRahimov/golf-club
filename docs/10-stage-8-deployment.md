# Этап 8: Развертывание

## Обзор этапа

**Цель:** Настроить production-среду и развернуть приложение.

**Длительность:** 1-2 дня

**Зависимости:** Все предыдущие этапы + тестирование

**Результат:** Работающее приложение на production-сервере.

---

## Чек-лист задач

- [ ] Подготовить сервер
- [ ] Настроить веб-сервер (Nginx)
- [ ] Настроить SSL-сертификат
- [ ] Настроить базу данных
- [ ] Развернуть приложение
- [ ] Настроить Supervisor для очередей
- [ ] Установить webhook для Telegram
- [ ] Настроить резервное копирование
- [ ] Настроить мониторинг

---

## 1. Требования к серверу

### 1.1 Минимальные требования

| Компонент | Требование |
|-----------|------------|
| ОС | Ubuntu 22.04 LTS |
| CPU | 2 vCPU |
| RAM | 2 GB |
| Диск | 20 GB SSD |
| PHP | 8.2+ |
| MySQL | 8.0+ |
| Nginx | 1.18+ |

### 1.2 Необходимое ПО

```bash
# Обновить систему
sudo apt update && sudo apt upgrade -y

# Установить необходимые пакеты
sudo apt install -y \
    nginx \
    mysql-server \
    php8.2-fpm \
    php8.2-mysql \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-curl \
    php8.2-zip \
    php8.2-gd \
    php8.2-redis \
    redis-server \
    supervisor \
    certbot \
    python3-certbot-nginx \
    git \
    unzip

# Установить Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

## 2. Настройка MySQL

### 2.1 Создание базы данных

```bash
sudo mysql -u root

# В MySQL консоли:
CREATE DATABASE golf_club CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'golf_club'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON golf_club.* TO 'golf_club'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 2.2 Настройка безопасности

```bash
sudo mysql_secure_installation
```

---

## 3. Настройка Nginx

### 3.1 Конфигурация сайта

**Файл:** `/etc/nginx/sites-available/golf-club`

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    
    # Редирект на HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    # SSL сертификаты (Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:50m;
    ssl_session_tickets off;

    # SSL настройки
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;

    # HSTS
    add_header Strict-Transport-Security "max-age=63072000" always;

    # Корневая директория
    root /var/www/golf-club/public;
    index index.php;

    # Логи
    access_log /var/log/nginx/golf-club-access.log;
    error_log /var/log/nginx/golf-club-error.log;

    # Gzip
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private auth;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml application/javascript;

    # Максимальный размер загружаемых файлов
    client_max_body_size 20M;

    # Основной location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Статические файлы
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|woff|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Запрет доступа к .env и другим скрытым файлам
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Запрет доступа к storage
    location ~ ^/storage/.*\.php$ {
        deny all;
    }
}
```

### 3.2 Активация сайта

```bash
# Создать символическую ссылку
sudo ln -s /etc/nginx/sites-available/golf-club /etc/nginx/sites-enabled/

# Проверить конфигурацию
sudo nginx -t

# Перезапустить Nginx
sudo systemctl reload nginx
```

---

## 4. SSL-сертификат (Let's Encrypt)

### 4.1 Получение сертификата

```bash
# Получить сертификат
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Проверить автообновление
sudo certbot renew --dry-run
```

### 4.2 Автообновление

```bash
# Добавить в crontab
sudo crontab -e

# Добавить строку:
0 0 1 * * /usr/bin/certbot renew --quiet
```

---

## 5. Развертывание приложения

### 5.1 Клонирование репозитория

```bash
# Создать директорию
sudo mkdir -p /var/www/golf-club
sudo chown -R $USER:$USER /var/www/golf-club

# Клонировать репозиторий
cd /var/www
git clone https://github.com/your-repo/golf-club.git golf-club
cd golf-club
```

### 5.2 Установка зависимостей

```bash
# Установить PHP зависимости
composer install --no-dev --optimize-autoloader

# Установить права
sudo chown -R www-data:www-data /var/www/golf-club
sudo chmod -R 755 /var/www/golf-club
sudo chmod -R 775 /var/www/golf-club/storage
sudo chmod -R 775 /var/www/golf-club/bootstrap/cache
```

### 5.3 Настройка окружения

```bash
# Скопировать .env
cp .env.example .env

# Редактировать .env
nano .env
```

**Файл:** `.env` (production)

```env
APP_NAME="Golf Club"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://yourdomain.com

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=golf_club
DB_USERNAME=golf_club
DB_PASSWORD=strong_password_here

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=public
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_WEBHOOK_URL=https://yourdomain.com/telegram/webhook
TELEGRAM_WEBHOOK_SECRET=your_secret_token
TELEGRAM_CHANNEL_ID=-1001234567890
```

### 5.4 Инициализация приложения

```bash
# Сгенерировать ключ
php artisan key:generate

# Создать символическую ссылку для storage
php artisan storage:link

# Запустить миграции
php artisan migrate --force

# Запустить seeders
php artisan db:seed --force

# Кэшировать конфигурацию
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Установить webhook
php artisan telegram:set-webhook
```

---

## 6. Настройка Supervisor

### 6.1 Конфигурация worker

**Файл:** `/etc/supervisor/conf.d/golf-club-worker.conf`

```ini
[program:golf-club-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/golf-club/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/golf-club/storage/logs/worker.log
stopwaitsecs=3600
```

### 6.2 Запуск Supervisor

```bash
# Обновить конфигурацию
sudo supervisorctl reread
sudo supervisorctl update

# Запустить workers
sudo supervisorctl start golf-club-worker:*

# Проверить статус
sudo supervisorctl status
```

---

## 7. Настройка Cron (Scheduler)

### 7.1 Добавление в crontab

```bash
# Редактировать crontab для www-data
sudo crontab -u www-data -e

# Добавить строку:
* * * * * cd /var/www/golf-club && php artisan schedule:run >> /dev/null 2>&1
```

---

## 8. Резервное копирование

### 8.1 Скрипт бэкапа

**Файл:** `/var/www/golf-club/scripts/backup.sh`

```bash
#!/bin/bash

# Переменные
BACKUP_DIR="/var/backups/golf-club"
DATE=$(date +%Y-%m-%d_%H-%M-%S)
DB_NAME="golf_club"
DB_USER="golf_club"
DB_PASS="your_password"
APP_DIR="/var/www/golf-club"
RETENTION_DAYS=7

# Создать директорию для бэкапов
mkdir -p $BACKUP_DIR

# Бэкап базы данных
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Бэкап файлов storage
tar -czf $BACKUP_DIR/storage_$DATE.tar.gz -C $APP_DIR/storage .

# Бэкап .env
cp $APP_DIR/.env $BACKUP_DIR/env_$DATE.backup

# Удалить старые бэкапы
find $BACKUP_DIR -type f -mtime +$RETENTION_DAYS -delete

echo "Backup completed: $DATE"
```

### 8.2 Автоматизация бэкапов

```bash
# Сделать скрипт исполняемым
chmod +x /var/www/golf-club/scripts/backup.sh

# Добавить в crontab
sudo crontab -e

# Добавить строки:
# Ежедневный бэкап в 3:00
0 3 * * * /var/www/golf-club/scripts/backup.sh >> /var/log/golf-club-backup.log 2>&1
```

---

## 9. Мониторинг

### 9.1 Логирование

**Файл:** `config/logging.php` (production настройки)

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'slack'],
        'ignore_exceptions' => false,
    ],

    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => 'warning',
        'days' => 14,
    ],

    'slack' => [
        'driver' => 'slack',
        'url' => env('LOG_SLACK_WEBHOOK_URL'),
        'username' => 'Golf Club Bot',
        'emoji' => ':boom:',
        'level' => 'critical',
    ],
],
```

### 9.2 Healthcheck endpoint

**Файл:** `routes/web.php`

```php
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        Redis::ping();
        
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'error' => $e->getMessage(),
        ], 500);
    }
});
```

### 9.3 Мониторинг с помощью внешних сервисов

- **UptimeRobot** - мониторинг доступности
- **Sentry** - отслеживание ошибок
- **Laravel Telescope** - debugging (только для staging)

---

## 10. Скрипт деплоя

### 10.1 Deploy script

**Файл:** `/var/www/golf-club/scripts/deploy.sh`

```bash
#!/bin/bash
set -e

echo "🚀 Starting deployment..."

cd /var/www/golf-club

# Включить режим обслуживания
php artisan down --message="Обновление системы. Вернемся через минуту." --retry=60

# Получить последние изменения
git fetch origin main
git reset --hard origin/main

# Установить зависимости
composer install --no-dev --optimize-autoloader --no-interaction

# Запустить миграции
php artisan migrate --force

# Очистить и перестроить кэш
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Перезапустить очереди
php artisan queue:restart

# Установить права
sudo chown -R www-data:www-data /var/www/golf-club
sudo chmod -R 775 storage bootstrap/cache

# Выключить режим обслуживания
php artisan up

echo "✅ Deployment completed successfully!"
```

### 10.2 Использование

```bash
# Сделать исполняемым
chmod +x /var/www/golf-club/scripts/deploy.sh

# Запустить деплой
./scripts/deploy.sh
```

---

## 11. Checklist перед запуском

### 11.1 Безопасность

- [ ] APP_DEBUG=false
- [ ] APP_ENV=production
- [ ] Уникальный APP_KEY
- [ ] Сложные пароли БД
- [ ] SSL сертификат установлен
- [ ] Webhook secret настроен
- [ ] Файлы .env недоступны извне

### 11.2 Производительность

- [ ] Кэш конфигурации создан
- [ ] Кэш маршрутов создан
- [ ] Кэш views создан
- [ ] Redis для очередей и сессий
- [ ] Gzip включен в Nginx

### 11.3 Функциональность

- [ ] Миграции выполнены
- [ ] Seeders запущены
- [ ] Storage link создан
- [ ] Telegram webhook установлен
- [ ] Queue workers запущены
- [ ] Scheduler работает

### 11.4 Мониторинг

- [ ] Логи настроены
- [ ] Бэкапы настроены
- [ ] Healthcheck работает
- [ ] Алерты настроены

---

## 12. Troubleshooting

### 12.1 Частые проблемы

**500 Internal Server Error**
```bash
# Проверить логи
tail -f /var/www/golf-club/storage/logs/laravel.log
tail -f /var/log/nginx/golf-club-error.log

# Проверить права
sudo chown -R www-data:www-data /var/www/golf-club
```

**Telegram webhook не работает**
```bash
# Проверить webhook
curl -X POST https://api.telegram.org/bot<TOKEN>/getWebhookInfo

# Переустановить webhook
php artisan telegram:set-webhook
```

**Queue jobs не выполняются**
```bash
# Проверить Supervisor
sudo supervisorctl status

# Перезапустить workers
sudo supervisorctl restart golf-club-worker:*
```

### 12.2 Полезные команды

```bash
# Проверить статус сервисов
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status mysql
sudo systemctl status redis
sudo supervisorctl status

# Перезапустить сервисы
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm

# Очистить кэш Laravel
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Посмотреть очередь
php artisan queue:monitor
php artisan queue:failed
```

---

## 13. Критерии завершения этапа

- [ ] Сервер настроен и работает
- [ ] SSL сертификат установлен
- [ ] Приложение развернуто
- [ ] База данных настроена
- [ ] Telegram webhook работает
- [ ] Queue workers работают
- [ ] Scheduler настроен
- [ ] Бэкапы автоматизированы
- [ ] Мониторинг настроен
- [ ] Документация обновлена
