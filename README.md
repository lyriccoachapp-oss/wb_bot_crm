# WorkBangers CRM & Telegram Bot

Комплексная система управления CRM с интеграцией в Telegram.

## Структура проекта

- `/api` - Laravel backend API (управление данными, аутентификация, OCR).
- `/bot` - PHP Telegram Bot (входная точка вебхука и WebApp).
- `/web` - Фронтенд панель управления (если используется отдельно).
- `/ocr` - Python-сервис для распознавания текста (PaddleOCR).

## Развертывание

Проект настроен на работу через Docker Compose.

```bash
docker-compose up -d
```

## Настройка

1. Скопируйте `api/.env.example` в `api/.env` и настройте подключение к БД и API ключи.
2. Настройте вебхук для Telegram бота на `https://your-domain.com/bot/index.php`.
