# 🧠 СИСТЕМНЫЙ ПРОМПТ АГЕНТА: WORKBANGERS CRM

> Версия: 1.0 | Дата: 2026-04-21

---

## 🎯 КОНТЕКСТ ПРОЕКТА

Проект расположен в: `/var/www/crm.workbangers.com`

Домен: `crm.workbangers.com`

Старый бот (только для анализа, не для изменений): `./old/`

---

## 🏗 АРХИТЕКТУРА (НЕЗЫБЛЕМАЯ)

```
Bot  →  API  →  MySQL (workbangers_crm)
Web  →  API
```

| Приложение | Папка   | Порт Docker | URL                                   |
|------------|---------|-------------|---------------------------------------|
| API        | ./api/  | 8072        | https://crm.workbangers.com/api/      |
| Bot        | ./bot/  | 8071        | https://crm.workbangers.com/bot/      |
| Web        | ./web/  | 8073        | https://crm.workbangers.com/          |

**КРИТИЧНО: API — единственная точка доступа к БД. Bot и Web работают ТОЛЬКО через HTTP API.**

---

## 🔐 СЕКРЕТЫ (всегда в .env, никогда в коде)

Обнаруженные секреты из старого кода (перенести в .env):

```env
# База данных
DB_HOST=localhost
DB_DATABASE=workbangers_crm
DB_USERNAME=wb_crm
DB_PASSWORD=WHiv776cms345oI

# Telegram Bot
TELEGRAM_BOT_TOKEN=7603079317:AAFfLKwDPO2TKyK553MYAfOXiESXflvJAVk

# OpenAI (для OCR/GPT)
OPENAI_API_KEY=sk-proj-AfgQqffuoMvZbCBKnymM0RzOmy69YGic9J2...
OPENAI_MODEL=gpt-4o-mini

# SMTP (Yandex)
MAIL_HOST=smtp.yandex.ru
MAIL_PORT=465
MAIL_USERNAME=sales@workbangers.com
MAIL_PASSWORD=czraerammqzmjunu
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=no-reply@workbangers.com
MAIL_FROM_NAME="WorkBangers CRM"

# OCR сервис (локальный)
OCR_ENDPOINT=http://localhost:8868/ocr

# Приложение
APP_URL=https://crm.workbangers.com
APP_TIMEZONE=America/Halifax
```

---

## 🗄 БАЗА ДАННЫХ

**БД**: `workbangers_crm`
**Пользователь**: `wb_crm`

### Существующие таблицы (НЕ ИЗМЕНЯТЬ структуру!):

| Таблица           | Назначение                                      |
|-------------------|-------------------------------------------------|
| `bot_users`       | Пользователи бота (telegram_id, имя, статус...) |
| `bot_operations`  | Текущая операция пользователя в боте            |
| `bot_places`      | Объекты (строительные/рабочие площадки)         |
| `bot_receipts`    | Чеки сотрудников                               |
| `bot_messages`    | Сообщения бота                                  |
| `bot_wp_tracking` | Трекинг геолокации                             |
| `bot_wp_worktime` | Учёт рабочего времени (основная таблица)       |

### Структура ключевых таблиц (из анализа кода):

**bot_users**: `id_telegram, id_chat, username, firstname, lastname, email, phone, addr, sin_num, status (new/inprogress/registred), admin (0/1), tester (0/1), invite, lcode, menu_msg_id`

**bot_places**: `id_place, active (0/1), place_name, place_address, gdrive_id, status`

**bot_wp_worktime**: `id_worktime, id_telegram, id_place, workday, checkin, checkout, lunchin, lunchout, gas_costs, latitude, longitude, photo, work_desc`

**bot_receipts**: `id_receipt, id_telegram, id_place, receipt_date, receipt_time, merchant_name, merchant_address, receipt_amount, receipt_type (category), payment_method, card_last4, gdrive_id, items_json, ocr_text, currency, subtotal, tax`

### Новые таблицы (создавать для новой логики):

```sql
-- Пользователи CRM (маппинг старых bot_users)
crm_users (id, bot_user_id, email, password_hash, role_id, ...)

-- Роли RBAC
crm_roles (id, name, slug)

-- Права
crm_permissions (id, name, slug)

-- Связь ролей и прав
crm_role_permissions (role_id, permission_id)

-- JWT refresh tokens
crm_tokens (id, user_id, token_hash, expires_at, created_at)

-- Сброс пароля
crm_password_resets (id, email, token_hash, expires_at)
```

---

## 🌐 API СТАНДАРТЫ

### Префикс: `/api/v1/`

### Формат ответа:
```json
{
  "success": true,
  "data": {},
  "error": null
}
```

### Пагинация: `?page=1&limit=20`

### Обязательные endpoints:

```
# Авторизация
POST /api/v1/auth/login
POST /api/v1/auth/telegram
GET  /api/v1/auth/me
POST /api/v1/auth/refresh
POST /api/v1/auth/logout
POST /api/v1/auth/forgot-password
POST /api/v1/auth/reset-password

# Пользователи
GET    /api/v1/users
GET    /api/v1/users/{id}
POST   /api/v1/users
PUT    /api/v1/users/{id}
DELETE /api/v1/users/{id}

# Объекты (читаем bot_places)
GET    /api/v1/objects
POST   /api/v1/objects
PUT    /api/v1/objects/{id}
DELETE /api/v1/objects/{id}
PATCH  /api/v1/objects/{id}/toggle

# Учёт времени (читаем bot_wp_worktime)
GET  /api/v1/time-entries
POST /api/v1/time-entries/check-in
POST /api/v1/time-entries/check-out
POST /api/v1/time-entries/lunch-in
POST /api/v1/time-entries/lunch-out
POST /api/v1/time-entries/{id}/location

# Чеки (читаем bot_receipts)
GET  /api/v1/receipts
POST /api/v1/receipts
PUT  /api/v1/receipts/{id}
POST /api/v1/receipts/upload
POST /api/v1/receipts/{id}/recognize

# Отчёты
GET /api/v1/reports/objects
GET /api/v1/reports/time
GET /api/v1/reports/receipts
GET /api/v1/reports/objects/xlsx
GET /api/v1/reports/time/xlsx

# Справочники (для OCR/бота)
GET /api/v1/references/employees
GET /api/v1/references/objects
```

---

## 🛠 ТЕХНОЛОГИЧЕСКИЙ СТЕК

### API (./api/)
- **Фреймворк**: Laravel (минимальная конфигурация)
- **Архитектура**: Controller → Service → Repository
- **Auth**: JWT (пакет `tymon/jwt-auth`)
- **БД**: MySQL через Eloquent ORM
- **Отчёты**: PhpSpreadsheet (XLSX)

### Bot (./bot/)
- **Язык**: PHP
- **Подход**: webhook, работает через HTTP к API
- **Webhook URL**: `https://crm.workbangers.com/bot/`
- **❌ Прямой доступ к БД запрещён**

### Web (./web/)
- **Язык**: PHP (шаблоны)
- **Шаблоны**: `./templates/` (соблюдать стиль!)
- **JS**: Vanilla JS
- **CSS**: чистый CSS
- **❌ НЕ использовать**: React, Vue, Angular

---

## 📋 RBAC (роли и права)

| Право               | Описание                      |
|---------------------|-------------------------------|
| `users.view`        | Просмотр пользователей        |
| `users.manage`      | Управление пользователями     |
| `objects.view`      | Просмотр объектов             |
| `objects.manage`    | Управление объектами          |
| `receipts.view`     | Просмотр своих чеков          |
| `receipts.view_all` | Просмотр всех чеков           |
| `receipts.manage`   | Управление чеками             |
| `time.view`         | Просмотр своего времени       |
| `time.view_all`     | Просмотр всего времени        |
| `time.manage`       | Управление временем           |
| `reports.view`      | Просмотр отчётов              |

| Роль    | Права                              |
|---------|------------------------------------|
| `admin` | Все права                          |
| `user`  | view своё + receipts + time своё   |

---

## 🤖 ОСОБЕННОСТИ СТАРОГО БОТА (для понимания логики)

### Регистрация (invite-flow):
1. `/start` → предлагает ввести invite-код
2. Invite → имя → фамилия → email → адрес → телефон → SIN-номер
3. После регистрации: `status = 'registred'`

### Роли:
- `admin = 1` → админ
- `admin = 0, tester = 1` → тестовый сотрудник
- `admin = 0, tester = 0` → обычный сотрудник

### Рабочее время:
- Привязка к объекту (bot_places)
- Check-in с геолокацией
- Check-out с описанием работ
- Обед (lunchin/lunchout)
- Расходы на газ

### Чеки:
- Загрузка фото → OCR (localhost:8868) → GPT-4o-mini разбор
- Сохранение на Google Drive
- Поля: дата, сумма, магазин, категория, способ оплаты, last4 карты

### Отчёт по объекту (obj-report.php):
- Рабочее время по дням / сотрудникам
- Чеки объекта
- Фильтрация по датам и сотрудникам
- Округление до 5 минут

---

## 🎨 ПРАВИЛА ФРОНТЕНДА

- HTML + CSS + Vanilla JS
- Комментарии на русском языке
- Адаптивность: от 720p, мобильные устройства
- Flexbox + Grid + media queries
- Стили из `./templates/`
- ❌ Нет горизонтального скролла
- ❌ Нет фиксированного layout

---

## 📚 ДОКУМЕНТАЦИЯ

```
./api/doc/   — документация API
./bot/doc/   — документация Bot
./web/doc/   — документация Web
```

Язык: русский. Принцип: **необходимо и достаточно**.

---

## 🐳 DOCKER

Каждое приложение — отдельный контейнер:
- `docker-compose.yml` в корне проекта
- MySQL — вне Docker (локально на сервере)
- API-контейнер использует `extra_hosts: host-gateway` для подключения к MySQL

---

## 📝 СТАНДАРТ КОДА (PHP)

- PHPDoc обязателен для всех классов и методов
- Комментарии на русском языке
- Отступы: **табуляция** (tab, 4 символа)
- После первого символа: пробелы для выравнивания
- Слои: Controller → Service → Repository

---

## 🧠 ПОВЕДЕНИЕ АГЕНТА

1. **Анализировать перед действием**
2. **Составлять план** → показывать пользователю
3. **Действовать по плану** (не отклоняться)
4. **Проверять результат**
5. **Обновлять документацию**

Если обнаружены противоречия или неясности — **остановиться и спросить**.

---

## ⚠️ ВАЖНЫЕ ОГРАНИЧЕНИЯ

1. ❌ Не изменять структуру таблиц `bot_*`
2. ❌ Не хранить секреты в коде
3. ❌ Bot и Web не работают напрямую с БД
4. ❌ Не дублировать бизнес-логику
5. ❌ Не использовать React/Vue без разрешения

---

## 🔄 ОБНОВЛЕНИЕ ЭТОГО ФАЙЛА

Если в процессе работы появляются новые важные правила или решения — добавлять их сюда.

---

*END OF SYSTEM PROMPT v1.0*
