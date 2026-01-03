# Полная инструкция по установке Golf Club на Ubuntu 22.04 VPS

> **Оптимизировано для:** 2 vCPU / 4 GB RAM

## Содержание

1. [Требования к серверу](#1-требования-к-серверу)
2. [Подключение к серверу](#2-подключение-к-серверу)
3. [Обновление системы](#3-обновление-системы)
4. [Генерация SSH-ключа для Git](#4-генерация-ssh-ключа-для-git)
5. [Установка Nginx](#5-установка-nginx)
6. [Установка PHP 8.2 (оптимизированная)](#6-установка-php-82-оптимизированная)
7. [Установка PostgreSQL (оптимизированная)](#7-установка-postgresql-оптимизированная)
8. [Установка Redis (оптимизированная)](#8-установка-redis-оптимизированная)
9. [Установка Composer](#9-установка-composer)
10. [Установка Node.js](#10-установка-nodejs)
11. [Создание пользователя для приложения](#11-создание-пользователя-для-приложения)
12. [Загрузка проекта через Git](#12-загрузка-проекта-через-git)
13. [Настройка проекта](#13-настройка-проекта)
14. [Настройка Nginx (оптимизированная)](#14-настройка-nginx-оптимизированная)
15. [Установка SSL-сертификата](#15-установка-ssl-сертификата)
16. [Настройка Supervisor (оптимизированная)](#16-настройка-supervisor-оптимизированная)
17. [Настройка Cron](#17-настройка-cron)
18. [Настройка Firewall](#18-настройка-firewall)
19. [Создание Telegram бота](#19-создание-telegram-бота)
20. [Установка Webhook](#20-установка-webhook)
21. [Создание администратора](#21-создание-администратора)
22. [Проверка работоспособности](#22-проверка-работоспособности)
23. [Обновление проекта (git pull)](#23-обновление-проекта-git-pull)
24. [Резервное копирование](#24-резервное-копирование)
25. [Мониторинг ресурсов](#25-мониторинг-ресурсов)
26. [Устранение неполадок](#26-устранение-неполадок)

---

## 1. Требования к серверу

### Рекомендуемая конфигурация
| Параметр | Значение |
|----------|----------|
| ОС | Ubuntu 22.04 LTS |
| CPU | **2 vCPU** |
| RAM | **4 GB** |
| Диск | 40 GB SSD |
| Сеть | Публичный IP-адрес |

### Распределение ресурсов (4 GB RAM)
| Компонент | Выделено RAM |
|-----------|--------------|
| PostgreSQL | ~1 GB |
| PHP-FPM | ~1.5 GB |
| Redis | ~512 MB |
| Nginx | ~256 MB |
| Система + Supervisor | ~768 MB |

### Что понадобится
- Доменное имя (например: `golfclub.example.com`)
- SSH-доступ к серверу (root или sudo)
- GitHub/GitLab репозиторий с проектом
- Telegram аккаунт для создания бота

---

## 2. Подключение к серверу

### Windows (PowerShell или PuTTY)
```bash
ssh root@YOUR_SERVER_IP
```

### Или с ключом SSH
```bash
ssh -i ~/.ssh/your_key root@YOUR_SERVER_IP
```

> 💡 Замените `YOUR_SERVER_IP` на IP-адрес вашего сервера

---

## 3. Обновление системы

### 3.1 Обновляем пакеты
```bash
apt update && apt upgrade -y
```

### 3.2 Устанавливаем базовые утилиты
```bash
apt install -y curl wget git unzip software-properties-common apt-transport-https ca-certificates gnupg lsb-release htop
```

### 3.3 Настройка swap (рекомендуется для 4GB RAM)
```bash
# Создаём swap файл 2GB
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile

# Добавляем в автозагрузку
echo '/swapfile none swap sw 0 0' >> /etc/fstab

# Настраиваем swappiness
echo 'vm.swappiness=10' >> /etc/sysctl.conf
sysctl -p
```

### 3.4 Перезагружаем сервер (если было обновление ядра)
```bash
reboot
```

После перезагрузки подключитесь снова.

---

## 4. Генерация SSH-ключа для Git

### 4.1 Генерируем SSH-ключ
```bash
ssh-keygen -t ed25519 -C "golfclub-server"
```

Нажмите Enter на все вопросы (оставляем пустой пароль для автоматизации).

### 4.2 Просматриваем публичный ключ
```bash
cat ~/.ssh/id_ed25519.pub
```

Вы увидите что-то вроде:
```
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAI... golfclub-server
```

### 4.3 Добавляем ключ в GitHub

1. Скопируйте вывод команды `cat ~/.ssh/id_ed25519.pub`
2. Откройте GitHub → Settings → SSH and GPG keys
3. Нажмите "New SSH key"
4. Title: `Golf Club Server`
5. Key: вставьте скопированный ключ
6. Нажмите "Add SSH key"

### 4.4 Добавляем ключ в GitLab (если используете GitLab)

1. Скопируйте вывод команды `cat ~/.ssh/id_ed25519.pub`
2. Откройте GitLab → Preferences → SSH Keys
3. Key: вставьте скопированный ключ
4. Title: `Golf Club Server`
5. Нажмите "Add key"

### 4.5 Проверяем подключение

**Для GitHub:**
```bash
ssh -T git@github.com
```

Ответ должен быть: `Hi username! You've successfully authenticated...`

**Для GitLab:**
```bash
ssh -T git@gitlab.com
```

### 4.6 Настраиваем Git
```bash
git config --global user.name "Golf Club Server"
git config --global user.email "server@golfclub.example.com"
```

---

## 5. Установка Nginx

### 5.1 Установка
```bash
apt install -y nginx
```

### 5.2 Запуск и автозагрузка
```bash
systemctl start nginx
systemctl enable nginx
```

### 5.3 Проверка статуса
```bash
systemctl status nginx
```

Вы должны увидеть `active (running)`.

### 5.4 Проверка в браузере
Откройте в браузере: `http://YOUR_SERVER_IP`

Вы должны увидеть страницу "Welcome to nginx!"

---

## 6. Установка PHP 8.2 (оптимизированная)

> ⚙️ **Оптимизировано для 2 CPU / 4 GB RAM**

### 6.1 Добавляем репозиторий PHP
```bash
add-apt-repository ppa:ondrej/php -y
apt update
```

### 6.2 Устанавливаем PHP и расширения
```bash
apt install -y php8.2-fpm php8.2-cli php8.2-common php8.2-mysql php8.2-pgsql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath php8.2-intl php8.2-redis php8.2-opcache
```

### 6.3 Проверка версии
```bash
php -v
```

### 6.4 Оптимизированная настройка PHP-FPM Pool

```bash
nano /etc/php/8.2/fpm/pool.d/www.conf
```

**Полностью замените содержимое на:**

```ini
[www]
user = www-data
group = www-data

listen = /run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; === ОПТИМИЗАЦИЯ ДЛЯ 2 CPU / 4 GB RAM ===

; Режим процессов: dynamic - оптимален для небольших серверов
pm = dynamic

; Максимум дочерних процессов (формула: RAM для PHP / memory_limit)
; 1536 MB / 128 MB = 12 процессов
pm.max_children = 12

; Процессы при старте (25% от max_children)
pm.start_servers = 3

; Минимум простаивающих процессов
pm.min_spare_servers = 2

; Максимум простаивающих процессов
pm.max_spare_servers = 5

; Запросов на процесс до перезапуска (защита от утечек памяти)
pm.max_requests = 500

; Таймаут запроса
request_terminate_timeout = 60s

; Slowlog для отладки
slowlog = /var/log/php-fpm-slow.log
request_slowlog_timeout = 5s

; Статус страница (для мониторинга)
pm.status_path = /fpm-status
ping.path = /fpm-ping
```

### 6.5 Оптимизированный php.ini

```bash
nano /etc/php/8.2/fpm/php.ini
```

Найдите и измените следующие параметры:

```ini
; === ОСНОВНЫЕ НАСТРОЙКИ ===
memory_limit = 128M
max_execution_time = 60
max_input_time = 60
max_input_vars = 3000

; === ЗАГРУЗКА ФАЙЛОВ ===
upload_max_filesize = 20M
post_max_size = 25M
max_file_uploads = 10

; === СЕССИИ ===
session.gc_maxlifetime = 1440

; === БЕЗОПАСНОСТЬ ===
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; === ЧАСОВОЙ ПОЯС ===
date.timezone = Asia/Tashkent

; === OPCACHE (ВАЖНО ДЛЯ ПРОИЗВОДИТЕЛЬНОСТИ) ===
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 32
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 60
opcache.validate_timestamps = 1
opcache.save_comments = 1
opcache.fast_shutdown = 1

; === REALPATH CACHE ===
realpath_cache_size = 4096K
realpath_cache_ttl = 600
```

### 6.6 Перезапуск PHP-FPM
```bash
systemctl restart php8.2-fpm
systemctl enable php8.2-fpm
systemctl status php8.2-fpm
```

---

## 7. Установка PostgreSQL (оптимизированная)

> ⚙️ **Оптимизировано для 2 CPU / 4 GB RAM**

### 7.1 Установка
```bash
apt install -y postgresql postgresql-contrib
```

### 7.2 Запуск и автозагрузка
```bash
systemctl start postgresql
systemctl enable postgresql
```

### 7.3 Создание базы данных и пользователя
```bash
sudo -u postgres psql
```

В консоли PostgreSQL выполните:
```sql
CREATE USER golfclub WITH PASSWORD 'your_secure_password_here';
CREATE DATABASE golfclub OWNER golfclub;
GRANT ALL PRIVILEGES ON DATABASE golfclub TO golfclub;
\q
```

> ⚠️ **ВАЖНО:** Замените `your_secure_password_here` на надёжный пароль!

### 7.4 Оптимизация PostgreSQL

```bash
nano /etc/postgresql/14/main/postgresql.conf
```

Найдите и измените следующие параметры:

```ini
# === ПАМЯТЬ (для 4 GB RAM, выделяем ~1 GB для PostgreSQL) ===

# Общая память для кэширования (25% RAM)
shared_buffers = 1GB

# Память для операций сортировки
work_mem = 32MB

# Память для служебных операций
maintenance_work_mem = 256MB

# Кэш эффективного размера (75% RAM)
effective_cache_size = 3GB

# === СОЕДИНЕНИЯ ===

# Максимум соединений (PHP workers * 2 + резерв)
max_connections = 50

# === WAL И CHECKPOINT ===

# Размер WAL буфера
wal_buffers = 16MB

# Интервал checkpoint
checkpoint_completion_target = 0.9

# Минимальный размер WAL
min_wal_size = 256MB
max_wal_size = 1GB

# === ПАРАЛЛЕЛЬНЫЕ ЗАПРОСЫ ===

# Воркеры на CPU
max_worker_processes = 2
max_parallel_workers_per_gather = 1
max_parallel_workers = 2

# === ЛОГИРОВАНИЕ ===

log_min_duration_statement = 1000
log_checkpoints = on
log_connections = on
log_disconnections = on
log_lock_waits = on

# === RANDOM PAGE COST (для SSD) ===
random_page_cost = 1.1
effective_io_concurrency = 200
```

### 7.5 Настройка pg_hba.conf

```bash
nano /etc/postgresql/14/main/pg_hba.conf
```

Убедитесь, что есть строка:
```
local   all             golfclub                                md5
host    all             golfclub        127.0.0.1/32            md5
```

### 7.6 Перезапуск PostgreSQL
```bash
systemctl restart postgresql
systemctl status postgresql
```

### 7.7 Проверка подключения
```bash
psql -U golfclub -d golfclub -h localhost -W
```

---

## 8. Установка Redis (оптимизированная)

> ⚙️ **Оптимизировано для 2 CPU / 4 GB RAM**

### 8.1 Установка
```bash
apt install -y redis-server
```

### 8.2 Оптимизированная настройка Redis

```bash
nano /etc/redis/redis.conf
```

Найдите и измените следующие параметры:

```ini
# === СЕТЬ ===
bind 127.0.0.1 ::1
port 6379
protected-mode yes

# === ПАМЯТЬ (выделяем 512 MB) ===
maxmemory 512mb
maxmemory-policy allkeys-lru

# === PERSISTENCE ===
# RDB снапшоты
save 900 1
save 300 10
save 60 10000

rdbcompression yes
rdbchecksum yes
dbfilename dump.rdb
dir /var/lib/redis

# AOF для надёжности
appendonly yes
appendfilename "appendonly.aof"
appendfsync everysec
auto-aof-rewrite-percentage 100
auto-aof-rewrite-min-size 64mb

# === ПРОИЗВОДИТЕЛЬНОСТЬ ===
tcp-keepalive 300
timeout 0

# Lazy free для фоновых операций
lazyfree-lazy-eviction yes
lazyfree-lazy-expire yes
lazyfree-lazy-server-del yes
replica-lazy-flush yes

# === СОЕДИНЕНИЯ ===
maxclients 100

# === SYSTEMD ===
supervised systemd

# === ЛОГИРОВАНИЕ ===
loglevel notice
logfile /var/log/redis/redis-server.log
```

### 8.3 Оптимизация системы для Redis

```bash
# Отключаем transparent huge pages
echo never > /sys/kernel/mm/transparent_hugepage/enabled
echo 'echo never > /sys/kernel/mm/transparent_hugepage/enabled' >> /etc/rc.local

# Увеличиваем vm.overcommit_memory
echo 'vm.overcommit_memory = 1' >> /etc/sysctl.conf

# Увеличиваем net.core.somaxconn
echo 'net.core.somaxconn = 512' >> /etc/sysctl.conf

sysctl -p
```

### 8.4 Запуск и автозагрузка
```bash
systemctl restart redis-server
systemctl enable redis-server
```

### 8.5 Проверка
```bash
redis-cli ping
# Ответ: PONG

redis-cli info memory | grep used_memory_human
# Показывает текущее использование памяти
```

---

## 9. Установка Composer

### 9.1 Скачивание и установка
```bash
cd ~
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
```

### 9.2 Проверка
```bash
composer --version
```

---

## 10. Установка Node.js

### 10.1 Добавление репозитория Node.js 20
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
```

### 10.2 Установка
```bash
apt install -y nodejs
```

### 10.3 Проверка
```bash
node -v
npm -v
```

---

## 11. Создание пользователя для приложения

### 11.1 Создаём пользователя
```bash
adduser --disabled-password --gecos "" golfclub
```

### 11.2 Добавляем в группу www-data
```bash
usermod -aG www-data golfclub
```

---

## 12. Загрузка проекта через Git

### 12.1 Создаём директорию
```bash
mkdir -p /var/www/golfclub
chown golfclub:www-data /var/www/golfclub
```

### 12.2 Клонируем репозиторий через SSH

> ⚠️ Убедитесь, что SSH-ключ добавлен в GitHub/GitLab (раздел 4)

**Для GitHub:**
```bash
cd /var/www/golfclub
sudo -u golfclub git clone git@github.com:YOUR_USERNAME/golf-club.git .
```

**Для GitLab:**
```bash
cd /var/www/golfclub
sudo -u golfclub git clone git@gitlab.com:YOUR_USERNAME/golf-club.git .
```

> 💡 Замените `YOUR_USERNAME` на ваш username и `golf-club` на имя репозитория

### 12.3 Если репозиторий приватный - добавьте Deploy Key

1. Сгенерируйте отдельный ключ для проекта:
```bash
sudo -u golfclub ssh-keygen -t ed25519 -C "golfclub-deploy" -f /home/golfclub/.ssh/id_deploy
```

2. Добавьте ключ в репозиторий:
   - GitHub: Repository → Settings → Deploy keys → Add deploy key
   - GitLab: Repository → Settings → Repository → Deploy keys

3. Настройте SSH конфиг:
```bash
sudo -u golfclub nano /home/golfclub/.ssh/config
```

```
Host github.com
    HostName github.com
    User git
    IdentityFile ~/.ssh/id_deploy
```

### 12.4 Проверка файлов
```bash
ls -la /var/www/golfclub
```

Вы должны увидеть файлы Laravel (artisan, composer.json, и т.д.)

---

## 13. Настройка проекта

### 12.1 Переходим в директорию проекта
```bash
cd /var/www/golfclub
```

### 12.2 Устанавливаем зависимости PHP
```bash
sudo -u golfclub composer install --no-dev --optimize-autoloader
```

### 12.3 Создаём файл .env
```bash
sudo -u golfclub cp .env.example .env
sudo -u golfclub nano .env
```

### 12.4 Настройка .env
Заполните следующие параметры:

```env
APP_NAME="Golf Club"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://golfclub.example.com

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=golfclub
DB_USERNAME=golfclub
DB_PASSWORD=your_secure_password_here

SESSION_DRIVER=redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_URL=https://golfclub.example.com/telegram/webhook
TELEGRAM_WEBHOOK_SECRET=your_random_secret_string
TELEGRAM_ADMIN_CHAT_ID=
```

> ⚠️ **ВАЖНО:** 
> - Замените `golfclub.example.com` на ваш домен
> - Замените `your_secure_password_here` на пароль от PostgreSQL
> - `TELEGRAM_BOT_TOKEN` заполним позже
> - `TELEGRAM_ADMIN_CHAT_ID` заполним позже

### 12.5 Генерируем ключ приложения
```bash
sudo -u golfclub php artisan key:generate
```

### 12.6 Создаём символическую ссылку для storage
```bash
sudo -u golfclub php artisan storage:link
```

### 12.7 Запускаем миграции
```bash
sudo -u golfclub php artisan migrate --force
```

### 12.8 Запускаем seeders
```bash
sudo -u golfclub php artisan db:seed --force
```

### 12.9 Кэшируем конфигурацию
```bash
sudo -u golfclub php artisan config:cache
sudo -u golfclub php artisan route:cache
sudo -u golfclub php artisan view:cache
```

### 12.10 Устанавливаем права
```bash
chown -R golfclub:www-data /var/www/golfclub
chmod -R 755 /var/www/golfclub
chmod -R 775 /var/www/golfclub/storage
chmod -R 775 /var/www/golfclub/bootstrap/cache
```

---

## 13. Настройка Nginx

### 13.1 Создаём конфигурацию сайта
```bash
nano /etc/nginx/sites-available/golfclub
```

### 13.2 Вставляем конфигурацию
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name golfclub.example.com;
    root /var/www/golfclub/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    # Логи
    access_log /var/log/nginx/golfclub-access.log;
    error_log /var/log/nginx/golfclub-error.log;

    # Максимальный размер загружаемых файлов
    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Статические файлы
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|woff|woff2)$ {
        expires 7d;
        add_header Cache-Control "public, immutable";
    }
}
```

> ⚠️ Замените `golfclub.example.com` на ваш домен!

### 13.3 Активируем сайт
```bash
ln -s /etc/nginx/sites-available/golfclub /etc/nginx/sites-enabled/
```

### 13.4 Удаляем default сайт (опционально)
```bash
rm /etc/nginx/sites-enabled/default
```

### 13.5 Проверяем конфигурацию
```bash
nginx -t
```

Должно показать: `syntax is ok` и `test is successful`

### 13.6 Перезапускаем Nginx
```bash
systemctl reload nginx
```

---

## 14. Установка SSL-сертификата

### 14.1 Устанавливаем Certbot
```bash
apt install -y certbot python3-certbot-nginx
```

### 14.2 Получаем сертификат
```bash
certbot --nginx -d golfclub.example.com
```

Следуйте инструкциям:
1. Введите email для уведомлений
2. Согласитесь с условиями (Y)
3. Выберите редирект HTTP → HTTPS (рекомендуется опция 2)

### 14.3 Проверка автообновления
```bash
certbot renew --dry-run
```

### 14.4 Автообновление сертификата
Certbot автоматически добавляет cron задачу. Проверка:
```bash
systemctl status certbot.timer
```

---

## 16. Настройка Supervisor (оптимизированная)

> ⚙️ **Оптимизировано для 2 CPU / 4 GB RAM**

Supervisor нужен для запуска queue worker в фоновом режиме.

### 16.1 Установка
```bash
apt install -y supervisor
```

### 16.2 Создаём конфигурацию воркера
```bash
nano /etc/supervisor/conf.d/golfclub-worker.conf
```

### 16.3 Оптимизированная конфигурация воркера

```ini
[program:golfclub-worker]
process_name=%(program_name)s_%(process_num)02d

; Команда с оптимальными параметрами для 2 CPU
command=php /var/www/golfclub/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --memory=128

autostart=true
autorestart=true
stopasgroup=true
killasgroup=true

user=golfclub

; Количество воркеров = количество CPU (2 CPU = 2 воркера)
numprocs=2

redirect_stderr=true
stdout_logfile=/var/www/golfclub/storage/logs/worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=5

; Таймаут для graceful shutdown
stopwaitsecs=3600

; Приоритет запуска
priority=999
```

### 16.4 Конфигурация Supervisor (опционально)
```bash
nano /etc/supervisor/supervisord.conf
```

Добавьте/измените в секции `[supervisord]`:
```ini
[supervisord]
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid
childlogdir=/var/log/supervisor
minfds=10000
minprocs=200
```

### 16.5 Обновляем Supervisor
```bash
supervisorctl reread
supervisorctl update
```

### 16.6 Запускаем воркеры
```bash
supervisorctl start golfclub-worker:*
```

### 16.7 Проверяем статус
```bash
supervisorctl status
```

Должно показать `RUNNING` для обоих процессов.

### 16.8 Полезные команды Supervisor
```bash
# Перезапуск всех воркеров
supervisorctl restart golfclub-worker:*

# Остановка воркеров
supervisorctl stop golfclub-worker:*

# Просмотр логов в реальном времени
tail -f /var/www/golfclub/storage/logs/worker.log
```

---

## 17. Настройка Cron

### 17.1 Открываем crontab для пользователя golfclub
```bash
crontab -u golfclub -e
```

### 16.2 Добавляем задачу Laravel Scheduler
```
* * * * * cd /var/www/golfclub && php artisan schedule:run >> /dev/null 2>&1
```

Сохраните и закройте (Ctrl+X, Y, Enter).

### 16.3 Проверка
```bash
crontab -u golfclub -l
```

---

## 17. Настройка Firewall

### 17.1 Устанавливаем UFW (если не установлен)
```bash
apt install -y ufw
```

### 17.2 Настраиваем правила
```bash
ufw default deny incoming
ufw default allow outgoing

ufw allow ssh
ufw allow 'Nginx Full'
```

### 17.3 Включаем Firewall
```bash
ufw enable
```

Подтвердите: `y`

### 17.4 Проверка статуса
```bash
ufw status
```

---

## 18. Создание Telegram бота

### 18.1 Открываем @BotFather в Telegram
1. Откройте Telegram
2. Найдите `@BotFather`
3. Начните диалог командой `/start`

### 18.2 Создаём нового бота
```
/newbot
```

### 18.3 Вводим имя бота
```
Golf Club Bot
```
(Это отображаемое имя)

### 18.4 Вводим username бота
```
golfclub_bot
```
(Должен заканчиваться на `bot`, например: `mygolfclub_bot`)

### 18.5 Получаем токен
BotFather выдаст токен вида:
```
7123456789:AAHxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

> ⚠️ **СОХРАНИТЕ ЭТОТ ТОКЕН!** Он понадобится дальше.

### 18.6 Узнаём свой Chat ID
1. Найдите в Telegram бота `@userinfobot`
2. Отправьте `/start`
3. Бот покажет ваш **Id** (числовой)

Это будет ваш `TELEGRAM_ADMIN_CHAT_ID` для получения уведомлений.

### 18.7 Обновляем .env
```bash
nano /var/www/golfclub/.env
```

Заполните:
```env
TELEGRAM_BOT_TOKEN=7123456789:AAHxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxx
TELEGRAM_ADMIN_CHAT_ID=123456789
```

### 18.8 Очищаем кэш
```bash
cd /var/www/golfclub
sudo -u golfclub php artisan config:cache
```

---

## 19. Установка Webhook

### 19.1 Устанавливаем webhook
```bash
cd /var/www/golfclub
sudo -u golfclub php artisan telegram:set-webhook
```

Должно показать: `Webhook set successfully: https://golfclub.example.com/telegram/webhook`

### 19.2 Проверка webhook
```bash
curl "https://api.telegram.org/bot<YOUR_TOKEN>/getWebhookInfo"
```

Замените `<YOUR_TOKEN>` на токен бота.

Вы должны увидеть JSON с вашим webhook URL.

---

## 20. Создание администратора

### 20.1 Создаём администратора через Artisan
```bash
cd /var/www/golfclub
sudo -u golfclub php artisan orchid:admin admin admin@example.com yourpassword
```

Замените:
- `admin` - имя пользователя
- `admin@example.com` - email для входа
- `yourpassword` - пароль

### 20.2 Проверяем вход
Откройте в браузере: `https://golfclub.example.com/admin`

Войдите с указанными данными.

---

## 21. Проверка работоспособности

### 21.1 Checklist проверки

| Проверка | Команда/Действие | Ожидаемый результат |
|----------|------------------|---------------------|
| Сайт открывается | Откройте `https://golfclub.example.com` | Страница Laravel |
| Админка работает | Откройте `/admin` | Страница входа |
| Вход в админку | Введите логин/пароль | Dashboard |
| PHP работает | `php -v` | PHP 8.2.x |
| PostgreSQL работает | `systemctl status postgresql` | active (running) |
| Redis работает | `redis-cli ping` | PONG |
| Nginx работает | `systemctl status nginx` | active (running) |
| PHP-FPM работает | `systemctl status php8.2-fpm` | active (running) |
| Supervisor работает | `supervisorctl status` | RUNNING |
| Telegram бот | Напишите `/start` боту | Ответ бота |
| SSL сертификат | Проверьте замок в браузере | Зелёный замок |

### 21.2 Тестирование бота
1. Найдите вашего бота в Telegram
2. Отправьте `/start`
3. Нажмите "Поделиться номером телефона"
4. Проверьте в админке "Новые заявки"

### 21.3 Просмотр логов

**Логи Laravel:**
```bash
tail -f /var/www/golfclub/storage/logs/laravel.log
```

**Логи Nginx:**
```bash
tail -f /var/log/nginx/golfclub-error.log
```

**Логи воркера:**
```bash
tail -f /var/www/golfclub/storage/logs/worker.log
```

---

## 23. Обновление проекта (git pull)

### 23.1 Скрипт для обновления проекта

Создаём скрипт деплоя:
```bash
nano /var/www/golfclub/deploy.sh
```

Содержимое:
```bash
#!/bin/bash

# Цвета для вывода
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

APP_DIR="/var/www/golfclub"
cd $APP_DIR

echo -e "${YELLOW}🚀 Начинаем обновление Golf Club...${NC}"

# 1. Включаем режим обслуживания
echo -e "${GREEN}[1/8] Включаем режим обслуживания...${NC}"
sudo -u golfclub php artisan down

# 2. Получаем последние изменения из Git
echo -e "${GREEN}[2/8] Получаем обновления из Git...${NC}"
sudo -u golfclub git pull origin main

# 3. Устанавливаем зависимости
echo -e "${GREEN}[3/8] Устанавливаем зависимости...${NC}"
sudo -u golfclub composer install --no-dev --optimize-autoloader --no-interaction

# 4. Выполняем миграции
echo -e "${GREEN}[4/8] Выполняем миграции...${NC}"
sudo -u golfclub php artisan migrate --force

# 5. Очищаем кэш
echo -e "${GREEN}[5/8] Очищаем кэш...${NC}"
sudo -u golfclub php artisan optimize:clear

# 6. Пересоздаём кэш
echo -e "${GREEN}[6/8] Пересоздаём кэш...${NC}"
sudo -u golfclub php artisan optimize
sudo -u golfclub php artisan view:cache

# 7. Перезапускаем воркеры
echo -e "${GREEN}[7/8] Перезапускаем воркеры...${NC}"
supervisorctl restart golfclub-worker:*

# 8. Выключаем режим обслуживания
echo -e "${GREEN}[8/8] Выключаем режим обслуживания...${NC}"
sudo -u golfclub php artisan up

echo -e "${GREEN}✅ Обновление завершено!${NC}"
```

### 23.2 Делаем скрипт исполняемым
```bash
chmod +x /var/www/golfclub/deploy.sh
```

### 23.3 Использование

**Для обновления проекта:**
```bash
/var/www/golfclub/deploy.sh
```

**Или вручную:**
```bash
cd /var/www/golfclub

# Получаем изменения
sudo -u golfclub git pull origin main

# Устанавливаем зависимости
sudo -u golfclub composer install --no-dev --optimize-autoloader

# Миграции
sudo -u golfclub php artisan migrate --force

# Очищаем и пересоздаём кэш
sudo -u golfclub php artisan optimize:clear
sudo -u golfclub php artisan optimize

# Перезапускаем воркеры
supervisorctl restart golfclub-worker:*
```

### 23.4 Откат к предыдущей версии
```bash
cd /var/www/golfclub

# Смотрим историю коммитов
sudo -u golfclub git log --oneline -10

# Откатываемся к нужному коммиту
sudo -u golfclub git checkout <commit_hash>

# Или к предыдущему коммиту
sudo -u golfclub git checkout HEAD~1

# После отката - повторяем шаги деплоя
```

---

## 24. Резервное копирование

### 24.1 Создаём скрипт бэкапа
```bash
nano /var/www/golfclub/backup.sh
```

### 24.2 Содержимое скрипта
```bash
#!/bin/bash

# Настройки
BACKUP_DIR="/var/backups/golfclub"
DATE=$(date +%Y-%m-%d_%H-%M-%S)
DB_NAME="golfclub"
DB_USER="golfclub"
APP_DIR="/var/www/golfclub"
RETENTION_DAYS=7

# Создаём директорию
mkdir -p $BACKUP_DIR

# Бэкап базы данных
PGPASSWORD="your_db_password" pg_dump -U $DB_USER -h localhost $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Бэкап файлов storage
tar -czf $BACKUP_DIR/storage_$DATE.tar.gz -C $APP_DIR/storage .

# Бэкап .env
cp $APP_DIR/.env $BACKUP_DIR/env_$DATE.backup

# Удаляем старые бэкапы
find $BACKUP_DIR -type f -mtime +$RETENTION_DAYS -delete

echo "Backup completed: $DATE"
```

### 24.3 Делаем скрипт исполняемым
```bash
chmod +x /var/www/golfclub/backup.sh
```

### 24.4 Добавляем в cron
```bash
crontab -e
```

Добавляем строку:
```
0 3 * * * /var/www/golfclub/backup.sh >> /var/log/golfclub-backup.log 2>&1
```

Бэкап будет выполняться каждый день в 3:00.

---

## 25. Мониторинг ресурсов

> ⚙️ **Для сервера 2 CPU / 4 GB RAM**

### 25.1 Проверка использования ресурсов

```bash
# Общая информация о системе
htop

# Использование памяти
free -m

# Использование диска
df -h

# Использование CPU
mpstat 1 5

# Топ процессов по памяти
ps aux --sort=-%mem | head -10

# Топ процессов по CPU
ps aux --sort=-%cpu | head -10
```

### 25.2 Мониторинг PostgreSQL

```bash
# Активные соединения
sudo -u postgres psql -c "SELECT count(*) FROM pg_stat_activity;"

# Размер базы данных
sudo -u postgres psql -c "SELECT pg_size_pretty(pg_database_size('golfclub'));"

# Медленные запросы (если включено логирование)
tail -f /var/log/postgresql/postgresql-14-main.log | grep duration
```

### 25.3 Мониторинг Redis

```bash
# Статистика Redis
redis-cli info stats

# Использование памяти Redis
redis-cli info memory

# Количество ключей
redis-cli dbsize

# Мониторинг в реальном времени
redis-cli monitor
```

### 25.4 Мониторинг PHP-FPM

```bash
# Статус PHP-FPM (если включен pm.status_path)
curl http://127.0.0.1/fpm-status

# Проверка процессов
ps aux | grep php-fpm | wc -l

# Логи медленных запросов
tail -f /var/log/php-fpm-slow.log
```

### 25.5 Мониторинг Nginx

```bash
# Статус Nginx
systemctl status nginx

# Логи доступа
tail -f /var/log/nginx/golfclub-access.log

# Логи ошибок
tail -f /var/log/nginx/golfclub-error.log

# Количество соединений
ss -s
```

### 25.6 Рекомендуемые лимиты для 2 CPU / 4 GB RAM

| Компонент | Параметр | Значение |
|-----------|----------|----------|
| PHP-FPM | max_children | 12 |
| PHP-FPM | memory_limit | 128M |
| PostgreSQL | max_connections | 50 |
| PostgreSQL | shared_buffers | 1GB |
| Redis | maxmemory | 512MB |
| Redis | maxclients | 100 |
| Supervisor | numprocs (workers) | 2 |

---

## 26. Устранение неполадок

### Проблема: 502 Bad Gateway
```bash
# Проверяем PHP-FPM
systemctl status php8.2-fpm

# Перезапускаем
systemctl restart php8.2-fpm
systemctl restart nginx
```

### Проблема: Permission denied
```bash
# Исправляем права
chown -R golfclub:www-data /var/www/golfclub
chmod -R 775 /var/www/golfclub/storage
chmod -R 775 /var/www/golfclub/bootstrap/cache
```

### Проблема: Бот не отвечает
```bash
# Проверяем webhook
curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"

# Проверяем логи
tail -f /var/www/golfclub/storage/logs/laravel.log

# Переустанавливаем webhook
cd /var/www/golfclub
sudo -u golfclub php artisan telegram:set-webhook
```

### Проблема: Очереди не работают
```bash
# Проверяем Supervisor
supervisorctl status

# Перезапускаем воркеры
supervisorctl restart golfclub-worker:*
```

### Проблема: Миграции не работают
```bash
# Проверяем подключение к БД
cd /var/www/golfclub
sudo -u golfclub php artisan tinker
>>> DB::connection()->getPdo();

# Если ошибка - проверяем .env
cat .env | grep DB_
```

### Полезные команды

```bash
# Очистка всего кэша
cd /var/www/golfclub
sudo -u golfclub php artisan optimize:clear

# Пересоздание кэша
sudo -u golfclub php artisan optimize

# Перезапуск всех сервисов
systemctl restart nginx php8.2-fpm redis-server postgresql
supervisorctl restart all

# Проверка использования диска
df -h

# Проверка использования памяти
free -m

# Проверка процессов
htop
```

---

## Итоговый checklist

После завершения установки убедитесь:

### Базовая установка
- [ ] Сервер обновлён, swap настроен (2 GB)
- [ ] SSH-ключ для Git сгенерирован и добавлен в GitHub/GitLab
- [ ] Nginx установлен и работает
- [ ] PHP 8.2 установлен и оптимизирован (max_children=12)
- [ ] PostgreSQL установлен и оптимизирован (shared_buffers=1GB)
- [ ] Redis установлен и оптимизирован (maxmemory=512mb)
- [ ] Composer установлен
- [ ] Node.js установлен

### Проект
- [ ] Проект клонирован через Git в `/var/www/golfclub`
- [ ] Зависимости установлены (`composer install`)
- [ ] Файл `.env` настроен
- [ ] Ключ приложения сгенерирован
- [ ] Миграции выполнены
- [ ] Seeders выполнены
- [ ] Storage link создан

### SSL и безопасность
- [ ] SSL сертификат установлен (Let's Encrypt)
- [ ] Firewall настроен (UFW)

### Фоновые процессы
- [ ] Supervisor настроен, воркеры работают (2 процесса)
- [ ] Cron настроен для Laravel Scheduler

### Telegram
- [ ] Telegram бот создан в @BotFather
- [ ] Токен добавлен в .env
- [ ] TELEGRAM_ADMIN_CHAT_ID настроен
- [ ] Webhook установлен и работает

### Администрирование
- [ ] Администратор создан (orchid:admin)
- [ ] Вход в админку работает
- [ ] Telegram бот отвечает на /start

### Обслуживание
- [ ] Скрипт бэкапа создан и добавлен в cron
- [ ] Скрипт деплоя (deploy.sh) создан

---

## Краткая справка по командам

```bash
# Обновление проекта
/var/www/golfclub/deploy.sh

# Перезапуск всех сервисов
systemctl restart nginx php8.2-fpm redis-server postgresql
supervisorctl restart all

# Просмотр логов
tail -f /var/www/golfclub/storage/logs/laravel.log

# Проверка статуса сервисов
systemctl status nginx php8.2-fpm redis-server postgresql
supervisorctl status

# Бэкап вручную
/var/www/golfclub/backup.sh
```

🎉 **Поздравляем! Установка завершена!**
