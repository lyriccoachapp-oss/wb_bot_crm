# WorkBangers CRM API — Документация v1

> Версия: 1.0 | Дата: 2026-04-21

---

## Общее

**Базовый URL:** `https://crm.workbangers.com/api/v1`

**Формат ответа:**
```json
{
  "success": true,
  "data": {},
  "message": "Текст сообщения",
  "error": null
}
```

**Авторизация:** Bearer JWT токен в заголовке `Authorization`.

**Пагинация:** `?page=1&limit=20`

Ответ с пагинацией:
```json
{
  "success": true,
  "data": {
    "items": [...],
    "total": 235,
    "per_page": 20,
    "current_page": 1,
    "last_page": 12
  }
}
```

---

## Авторизация `/auth`

### POST /auth/login
Вход по email и паролю.

**Тело запроса:**
```json
{ "email": "admin@workbangers.com", "password": "Admin@2026!" }
```

**Ответ:**
```json
{
  "access_token": "eyJ...",
  "token_type": "bearer",
  "expires_in": 3600,
  "refresh_token": "...",
  "user": { "id": 1, "email": "...", "role": "admin" }
}
```

---

### POST /auth/telegram
Вход через Telegram Login Widget.

**Тело запроса:** данные от Telegram (id, first_name, hash, auth_date, ...)

---

### GET /auth/me 🔒
Текущий пользователь.

---

### POST /auth/refresh
**Тело:** `{ "refresh_token": "..." }`

---

### POST /auth/logout 🔒
Отзыв всех токенов.

---

### POST /auth/forgot-password
**Тело:** `{ "email": "user@example.com" }`

---

### POST /auth/reset-password
**Тело:** `{ "token": "...", "password": "NewPass@123", "password_confirmation": "NewPass@123" }`

---

## Пользователи `/users` 🔒 (требует `users.view`)

### GET /users
Список из `bot_users`. Параметры: `?page=&limit=`

### GET /users/{id}
Пользователь по telegram_id.

### POST /users (требует `users.manage`)
**Тело:** `{ "email": "...", "password": "...", "role_id": 1 }`

### PUT /users/{id} (требует `users.manage`)
**Тело:** `{ "role_id": 2, "active": true, "password": "..." }`

---

## Объекты `/objects` 🔒 (требует `objects.view`)

### GET /objects
Список объектов. Параметры: `?page=&limit=&active_only=true`

**Пример ответа:**
```json
{
  "id": 209,
  "name": "1014 Cow Bay Rd, Cow Bay",
  "address": "",
  "active": true
}
```

### POST /objects (требует `objects.manage`)
**Тело:** `{ "place_name": "Новый объект", "place_address": "123 Main St" }`

### PUT /objects/{id} (требует `objects.manage`)

### DELETE /objects/{id} (требует `objects.manage`)

### PATCH /objects/{id}/toggle (требует `objects.manage`)
Переключить активность объекта.

---

## Учёт времени `/time-entries` 🔒

### GET /time-entries
Параметры: `?page=&limit=&telegram_id=&place_id=&date_from=YYYY-MM-DD&date_to=YYYY-MM-DD`

Сотрудник видит только своё время. Администратор — всё.

**Пример записи:**
```json
{
  "id": 12345,
  "telegram_id": 123456789,
  "place_id": 209,
  "place_name": "1014 Cow Bay Rd",
  "workday": "2026-04-21",
  "checkin": "2026-04-21 08:00:00",
  "checkout": "2026-04-21 17:00:00",
  "lunchin": "2026-04-21 12:00:00",
  "lunchout": "2026-04-21 12:30:00",
  "gas_costs": 0,
  "work_seconds": 30600,
  "work_minutes_rounded": 510,
  "latitude": 44.6488,
  "longitude": -63.5752
}
```

### POST /time-entries/check-in
**Тело:** `{ "place_id": 209, "latitude": 44.6, "longitude": -63.5 }`

### POST /time-entries/check-out
**Тело:** `{ "work_desc": "Покраска стен, укладка плитки" }`

### POST /time-entries/lunch-in
Без тела.

### POST /time-entries/lunch-out
Без тела.

### POST /time-entries/{id}/location
**Тело:** `{ "latitude": 44.648, "longitude": -63.575 }`

---

## Чеки `/receipts` 🔒

### GET /receipts
Параметры: `?page=&limit=&telegram_id=&place_id=&date_from=&date_to=`

### POST /receipts/upload
Загрузка фото чека + OCR распознавание.

**Запрос:** `multipart/form-data`
- `file` — изображение (jpg/jpeg/png/webp, до 20MB)
- `place_id` — ID объекта (опционально)

**Ответ включает:**
```json
{
  "receipt": { ... },
  "parsed": {
    "merchant_name": "The Home Depot",
    "receipt_amount": "45.67",
    "receipt_date": "2026-04-21",
    "receipt_type": "materials"
  }
}
```

### PUT /receipts/{id} (требует `receipts.manage`)

---

## Отчёты `/reports` 🔒 (требует `reports.view`)

### GET /reports/objects
Параметры: `?place_id=209&date_from=2026-04-01&date_to=2026-04-30`

**Ответ:**
```json
{
  "place": { "id": 209, "name": "..." },
  "date_from": "2026-04-01",
  "date_to": "2026-04-30",
  "work_by_day": [
    {
      "date": "2026-04-21",
      "total_min": 510,
      "hours": 8,
      "minutes": 30,
      "employees": ["Ivan Petrov", "John Smith"],
      "count": 2
    }
  ],
  "work_total_h": 120,
  "work_total_m": 30,
  "receipts": [...],
  "receipts_total": 287.45
}
```

### GET /reports/objects/xlsx
Скачать XLSX. Те же параметры.

---

## Справочники `/references` 🔒

### GET /references/employees
```json
[
  { "id_telegram": 123456789, "name": "Ivan Petrov" },
  ...
]
```

### GET /references/objects
```json
[
  { "id": 209, "name": "1014 Cow Bay Rd, Cow Bay" },
  ...
]
```

---

## Публичные запросы

### GET /health
```json
{ "status": "ok", "version": "v1", "timestamp": "..." }
```

---

## Коды ошибок

| Код | Описание |
|-----|---------|
| 200 | Успех |
| 201 | Создано |
| 400 | Неверный запрос |
| 401 | Не авторизован / токен недействителен |
| 403 | Недостаточно прав |
| 404 | Не найдено |
| 409 | Конфликт (например, check-in уже выполнен) |
| 422 | Ошибка валидации |
| 500 | Внутренняя ошибка сервера |
