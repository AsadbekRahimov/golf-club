# Golf Club - Система управления подписками

Система управления подписками гостей гольф-клуба с администраторской панелью (Laravel Orchid) и Telegram-ботом для клиентов.

## Возможности

- **Администраторская панель** - управление клиентами, подписками, платежами, шкафами
- **Telegram-бот** - регистрация клиентов, бронирование услуг, отправка чеков
- **Аналитический Dashboard** - графики, метрики, фильтрация по периодам
- **Экспорт в Excel** - выгрузка данных на всех страницах
- **Ежедневные отчёты** - автоматическая отправка в Telegram
- **Система уведомлений** - уведомления клиентов о статусах

## Требования

- PHP 8.2+
- PostgreSQL 15+ или MySQL 8+
- Composer
- Node.js & NPM

## Установка

### 1. Клонирование и установка зависимостей

```bash
git clone <repository-url>
cd golf-club
composer install
npm install
```

### 2. Настройка окружения

```bash
cp .env.example .env
php artisan key:generate
```

Отредактируйте `.env` файл:

```env
# База данных
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=golf_club
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Telegram Bot
TELEGRAM_BOT_TOKEN=ваш_токен_бота
TELEGRAM_WEBHOOK_URL=https://ваш-домен.com/telegram/webhook
TELEGRAM_WEBHOOK_SECRET=случайная_строка_для_безопасности
TELEGRAM_CHANNEL_ID=-1001234567890
```

### 3. Миграции и начальные данные

```bash
php artisan migrate
php artisan db:seed
```

### 4. Сборка assets

```bash
npm run build
```

### 5. Настройка Telegram Webhook

```bash
php artisan telegram:set-webhook
```

## Учетные данные администратора

После выполнения seeders создается тестовый администратор:

- **Email:** admin@admin.com
- **Пароль:** password

Панель администратора: `/admin`

## Scheduler (Планировщик задач)

Добавьте в crontab:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Автоматически выполняются:
- `09:00` - Уведомления об истекающих подписках
- `00:05` - Обработка истекших подписок
- `23:50` - Ежедневный отчёт в Telegram

## Artisan команды

```bash
# Проверка истекающих подписок
php artisan subscriptions:check-expiring

# Обработка истекших подписок
php artisan subscriptions:process-expired

# Ежедневный отчёт
php artisan report:daily

# Установка Telegram webhook
php artisan telegram:set-webhook
```

## Структура проекта

```
app/
├── Enums/          # Статусы (ClientStatus, BookingStatus, etc.)
├── Models/         # Eloquent модели
├── Services/       # Бизнес-логика
├── Telegram/       # Telegram Bot
│   ├── Handlers/   # Обработчики сообщений
│   └── Keyboards/  # Клавиатуры бота
└── Orchid/         # Админ-панель
    ├── Screens/    # Экраны
    └── Layouts/    # Layouts и Charts
```

## Документация

Подробная документация в папке `/docs`:

- `00-prd-overview.md` - Общий обзор проекта
- `01-technical-architecture.md` - Техническая архитектура
- `02-database-design.md` - Проектирование БД
- `03-stage-1-database.md` - Миграции и модели
- `04-stage-2-admin-panel.md` - Админ-панель
- `05-stage-3-telegram-bot.md` - Telegram бот
- И другие...

## Лицензия

MIT License
