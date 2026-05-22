# AIditorium

AIditorium - backend API на Laravel 12 для учебных курсов, дисциплин, заданий, файлов, комментариев, оценок, peer review и AI-review работ студентов.

## Возможности backend

- JWT-аутентификация: регистрация, вход, выход и обновление токена.
- Пользователи, роли приложения и аватары пользователей.
- Курсы с участниками, ролями в курсе, кодами приглашения, закрытием, переоткрытием, архивированием и восстановлением.
- Дисциплины внутри курсов.
- Задания с дедлайном, баллами, материалами, несколькими вложениями и студенческими сдачами.
- Файлы на Laravel `public` disk, скачивание файлов и хранение метаданных загрузки.
- Комментарии к курсам, заданиям, дисциплинам и файлам, включая ответы.
- Оценки преподавателя, AI-оценки и статистика по оценкам.
- Назначение проверяющих преподавателей на задания.
- Peer review: настройки, назначения студентов на взаимную проверку и результаты проверки.
- AI-review: профиль проверки задания, очередь проверки сдачи, результат, рекомендованная оценка и применение оценки отдельным действием преподавателя.

## Backend-стек

- PHP `8.2+`
- Laravel `12`
- MariaDB
- JWT auth через `tymon/jwt-auth`
- Laravel queues с `database` connection по умолчанию
- Scramble для OpenAPI-документации
- PHPUnit для unit и feature тестов

## Структура backend

- `app/` - модели, контроллеры, политики, сервисы, jobs и AI-модуль.
- `routes/api.php` - основные API-маршруты.
- `routes/web.php` - корневой web route с Laravel welcome view.
- `database/migrations/` - схема базы данных.
- `database/seeders/` - начальные роли, permissions и admin-пользователь.
- `config/ai.php` - настройки AI-review, моделей, лимитов извлечения и server evaluator.
- `config/auth.php` и `config/jwt.php` - JWT-аутентификация.
- `lang/ru` и `lang/en` - локализация сообщений и описаний API.
- `tests/` - unit и feature тесты backend.

## Локальный запуск backend

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

После запуска API доступно от `APP_URL`, обычно `http://localhost:8000`.

## Настройка `.env`

Фактический пример переменных находится в `.env.example`. Для локального backend-запуска важны следующие группы настроек:

```env
APP_URL=http://localhost
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=Aiditorium
DB_USERNAME=root
DB_PASSWORD=

FILESYSTEM_DISK=public
QUEUE_CONNECTION=database

AI_PROVIDER=...
AI_BASE_URL=...
AI_API_KEY=...
AI_MODEL=...
AI_DEFAULT_MODEL_KEY=minimax

AI_DISPATCH_MODE=after_response
AI_QUEUE_CONNECTION=
AI_QUEUE=
AI_JOB_TRIES=3
AI_JOB_TIMEOUT=1800
AI_JOB_BACKOFF=30,90,180

AI_EXECUTION_ENABLED=true
AI_EXECUTION_TIMEOUT=15
AI_PHP_BINARY=
```

Также настраиваются лимиты извлечения данных для AI-review:

- `AI_MAX_EXTRACTED_CHARS`
- `AI_MAX_EXCERPT_CHARS`
- `AI_MAX_FILES_PER_REVIEW`
- `AI_MAX_CSV_PREVIEW_ROWS`
- `AI_MAX_SHEET_PREVIEW_ROWS`
- `AI_MAX_SHEET_PREVIEW_COLUMNS`
- `AI_ZIP_MAX_ENTRIES`
- `AI_ZIP_MAX_DEPTH`
- `AI_ZIP_MAX_TOTAL_UNCOMPRESSED_BYTES`

## API и документация

Основные API-маршруты находятся под префиксом `/api`.

Документация Scramble после запуска приложения:

- UI: [`/docs/api`](http://localhost:8000/docs/api)
- OpenAPI JSON: [`/docs/api.json`](http://localhost:8000/docs/api.json)

Scramble строит документацию по текущим маршрутам, FormRequest-валидации и переводам из `lang/`.

## Основные группы API

- Auth: `/api/register`, `/api/login`, `/api/logout`, `/api/refresh`.
- Users: CRUD для пользователей, редактирование профиля, установка роли, загрузка и удаление аватара.
- Courses: CRUD, просмотр своих курсов, участники курса, invite codes, закрытие, переоткрытие, архив и восстановление.
- Disciplines: CRUD, список дисциплин и поиск дисциплины по slug внутри курса.
- Tasks: CRUD, список заданий, просмотр по номеру, вложения, сдачи студентов, назначенные проверяющие.
- Files: загрузка, просмотр, скачивание, файлы курса и файлы студентов.
- Comments: комментарии курса, задания, свои комментарии и ответы на комментарий.
- Grades: CRUD, оценки курса, свои оценки, оценки студента и статистика.
- Peer review: настройки задания, назначения, список своих назначений и сохранение результатов.
- AI-review: профиль проверки задания, запуск проверки сдачи, список проверок, просмотр результата и применение рекомендованной оценки.

## Файлы

Backend использует Laravel `public` disk. Для доступа к загруженным файлам нужен симлинк:

```bash
php artisan storage:link
```

Для загруженных файлов сохраняются метаданные:

- `original_name`
- `mime_type`
- `extension`
- `size_bytes`

Обычный файл ограничен `10 MB` в request-валидации. Аватар должен быть изображением `jpeg`, `png`, `jpg` или `webp`, не больше `3 MB`, с размерами от `100x100` до `2000x2000`.

## AI-review

AI-review запускается преподавателем для конкретной студенческой сдачи. Студент не может запустить или просмотреть AI-review.

Процесс проверки:

1. Преподаватель настраивает профиль проверки задания.
2. Профиль хранит `enabled`, rubric, custom prompt, supported formats, ключ модели и версию.
3. Преподаватель ставит сдачу в AI-review.
4. Backend создает `ai_review_runs` со статусом `queued`.
5. Job извлекает содержимое файла, выполняет поддерживаемые серверные проверки и отправляет оставшиеся критерии в AI-модель.
6. Результат сохраняется в `ai_review_runs`.
7. Рекомендованная оценка применяется в `grades` только отдельным endpoint преподавателя.

Статусы проверки:

- `queued`
- `extracting`
- `analyzing`
- `completed`
- `failed`

В выборе модели доступны:

- `minimax`
- `deepseek`

В backend-конфиге DeepSeek хранится как ключ `deepseek_v4`.

## Извлечение сдач для AI-review

Поддерживаемые форматы берутся из `config/ai.php`.

Извлекаются:

- text/code файлы: `txt`, `md`, `json`, `xml`, `yml`, `yaml`, `ini`, `env`, `php`, `js`, `ts`, `jsx`, `tsx`, `vue`, `py`, `java`, `kt`, `cs`, `go`, `rs`, `rb`, `c`, `cpp`, `swift`, `sql`, `sh`, `ps1`, `html`, `css` и другие расширения из конфига;
- `docx` - текст и часть Office metadata;
- `xlsx` - preview листов;
- `csv` и `tsv` - preview строк и текстовый excerpt;
- `zip` - дерево архива и поддерживаемые вложенные файлы.

Ограничения:

- `.doc` и `.xls` принимаются, но полноценное извлечение текста или таблиц для них не реализовано.
- `.rar` и `.7z` принимаются как неподдерживаемые архивы без извлечения.
- ZIP с path traversal или превышением лимитов отклоняется.
- Текст нормализуется в UTF-8; есть тест на Windows-1251 входные данные с русским текстом.

## Server evaluator

Часть критериев AI-review проверяется на сервере без участия модели:

- compile/syntax checks для `php`, `py`, `js`, `cpp`, `cs` при наличии нужного runtime или compiler;
- HTML markup validation;
- базовая CSS structural validation;
- структурные проверки кода: количество методов/функций, CRUD-методы, признаки Laravel controller.

Если нужный runtime или compiler не найден, критерий получает статус `unsupported`. Запуск произвольных тестов, Docker, sandbox-сценариев и runtime-команд из критериев не выполняется и относится к неподдерживаемым проверкам.

## Очереди и фоновые задачи

AI-review обрабатывает `ProcessAiReviewRunJob`.

В `.env.example` режим запуска AI-review задан через `AI_DISPATCH_MODE=after_response`. Также поддерживаются режимы:

- `sync` - выполнить job синхронно;
- `after_response` - выполнить после отправки HTTP-ответа;
- `queue` - отправить job в Laravel queue.

Если используется `AI_DISPATCH_MODE=queue`, нужен queue worker, например:

```bash
php artisan queue:listen --tries=1
```

Для job настраиваются `AI_JOB_TRIES`, `AI_JOB_TIMEOUT` и `AI_JOB_BACKOFF`.

## Тесты

```bash
composer test
```

Текущие тесты покрывают AI-review flow, извлечение файлов, server evaluator, сборку результата проверки, клиент AI-модели, slug helper и регрессии контроллеров.
