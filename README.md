# AIditorium

AIditorium — это backend API-платформа на Laravel для учебных курсов, дисциплин, заданий, сдач, комментариев, оценок и AI-автопроверки работ студентов.

Проект ориентирован на работу как серверное API для сайта/клиента: здесь есть JWT-аутентификация, файловые загрузки, курсы и роли в курсе, очереди для фоновых задач и автогенерируемая OpenAPI-документация.

## Что умеет проект

- Управление пользователями и ролями
- Работа с курсами и дисциплинами
- Создание заданий и прикрепление материалов
- Загрузка студенческих сдач
- Комментарии по курсам и заданиям
- Ручные оценки преподавателя
- AI-автопроверка сдач с teacher-only критериями проверки

## AI-автопроверка

В проекте реализован backend-first модуль AI-проверки:

- преподаватель настраивает профиль AI-проверки для задания;
- профиль хранит структурированные критерии, поддерживаемые форматы и свободный prompt;
- преподаватель ставит конкретную сдачу в очередь на проверку;
- Laravel извлекает содержимое файла, нормализует текст в UTF-8 и отправляет подготовленный payload в абстрактный LLM-service;
- результат сохраняется как AI-review с отчетом, рекомендованной оценкой и статусом выполнения;
- итоговая оценка в `grades` применяется только явным teacher action.

### Поддерживаемые форматы v1

- Код и текстовые файлы: `php`, `js`, `ts`, `py`, `java`, `cs`, `sql`, `html`, `css`, `json`, `xml`, `yaml`, `md`, `txt` и др.
- Документы Word: `docx`
- Таблицы: `xlsx`, `csv`, `tsv`
- Архивы: `zip`

Ограничения v1:

- чужой код не выполняется на хостинге;
- проверки “скомпилировать”, “запустить”, “прогнать тесты” не исполняются и возвращаются как `unsupported_checks`;
- legacy-форматы `doc` и `xls` принимаются, но без полноценного структурного извлечения.

## Технологический стек

- PHP `8.2+`
- Laravel `12`
- MariaDB
- JWT auth через `tymon/jwt-auth`
- Очереди Laravel через `database` driver
- Vite + Tailwind CSS
- Scramble для OpenAPI/Swagger-like docs
- OpenRouter как первый production-адаптер LLM

## Структура репозитория

- `app/` — доменная логика, контроллеры, модели, сервисы, AI-модуль
- `routes/api.php` — основной API
- `database/migrations/` — схема БД
- `lang/` — RU/EN локализация сообщений и описаний API
- `config/ai.php` — настройки AI-провайдера и лимитов извлечения
- `CodeAnalyzer/` — старый C#-прототип, нужен как справочный материал и не участвует в runtime/deploy Laravel-приложения

## Локальный запуск

### 1. Подготовка

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

### 2. Настройка `.env`

Обязательные переменные:

```env
APP_URL=http://localhost

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=Aiditorium
DB_USERNAME=root
DB_PASSWORD=

FILESYSTEM_DISK=public
QUEUE_CONNECTION=database

AI_PROVIDER=openrouter
AI_BASE_URL=https://openrouter.ai/api/v1
AI_API_KEY=your_key_here
AI_MODEL=openai/gpt-4.1-mini
AI_TIMEOUT=120
```

Дополнительно можно настраивать лимиты AI-извлечения:

- `AI_MAX_EXTRACTED_CHARS`
- `AI_MAX_EXCERPT_CHARS`
- `AI_MAX_FILES_PER_REVIEW`
- `AI_ZIP_MAX_ENTRIES`
- `AI_ZIP_MAX_TOTAL_UNCOMPRESSED_BYTES`

### 3. Запуск приложения

Отдельно:

```bash
php artisan serve
php artisan queue:listen --tries=1
npm install
npm run dev
```

Или через composer script:

```bash
composer run dev
```

## Работа с файлами

Проект использует `public` disk Laravel и ожидает симлинк:

```bash
php artisan storage:link
```

Все загружаемые файлы сохраняют метаданные:

- `original_name`
- `mime_type`
- `extension`
- `size_bytes`

Это важно и для обычных загрузок, и для AI-автопроверки.

## API-документация

После запуска документация доступна по адресам:

- UI: [`/docs/api`](http://localhost/docs/api)
- OpenAPI JSON: [`/api.json`](http://localhost/api.json)

Scramble генерирует описание на основе текущих маршрутов, request validation и переводов из `lang/`.

## Очереди и фоновые задачи

AI-review работает через очередь:

`queued -> extracting -> analyzing -> completed/failed`

Для production окружения нужен запущенный queue worker. Без него AI-проверки не будут завершаться.

## Quick Start (EN)

AIditorium is a Laravel 12 backend API for courses, tasks, submissions, grades, comments, and AI-assisted student work review.

Core stack:

- Laravel 12
- PHP 8.2+
- MariaDB
- JWT auth
- Database queues
- Scramble API docs
- OpenRouter-backed LLM adapter

Quick start:

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
composer run dev
```

## Важно про `CodeAnalyzer`

Папка `CodeAnalyzer` не является частью runtime этого Laravel-проекта.

Это отдельный старый C#-prototype, сохраненный в репозитории как источник идей и исторический reference для модуля проверки. Деплой, хостинг и текущее API AIditorium на него не опираются.
