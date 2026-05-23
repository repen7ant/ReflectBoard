# ReflectBoard

![CI](https://github.com/repen7ant/ReflectBoard/actions/workflows/ci.yml/badge.svg)

Трекер задач с рефлексией. Kanban-доска для ведения дел, журнал выполненного с фильтрацией, аналитика продуктивности по времени и категориям — всё обновляется в реальном времени через WebSocket.

---

## Архитектура

```
Браузер
  │
  ├─ GET /board, /done, /analytics  ──►  Laravel (PHP-FPM)  ──►  Blade + Vite
  │                                            │
  │                                       сессии / auth
  │
  ├─ REST /api/v1/*  ──────────────────►  FastAPI (Python)
  │       Bearer JWT                          │         │
  │                                         MySQL     Redis
  │
  └─ WS  /api/v1/ws?token=...  ────────►  FastAPI WebSocket
                                              │
                                         Redis pub/sub
                                         board:{user_id}

Nginx — обратный прокси:
  :80 /api/*  →  FastAPI :8000
  :80 /*      →  Laravel/Nginx static + PHP-FPM :9000
  :5173       →  Vite dev server (только dev)
```

**Dual-Write:** при завершении задачи или создании quick capture FastAPI одновременно пишет в MySQL и инкрементирует счётчики в Redis, затем публикует событие в Redis-канал → все открытые вкладки получают обновление без перезагрузки.

---

## Запуск (dev)

```bash
# 1. Клонировать репозиторий
git clone https://github.com/<owner>/ReflectBoard.git
cd ReflectBoard

# 2. Скопировать .env файлы
cp laravel/.env.example laravel/.env
cp fastapi/.env.example fastapi/.env

# 3. Поднять контейнеры
docker compose up -d

# 4. Миграции запускаются автоматически при старте laravel-контейнера.
#    Если нужно запустить вручную:
docker compose exec laravel php artisan migrate

# 5. Открыть в браузере
http://localhost
```

> Vite dev server доступен на `:5173` и подключается автоматически через Laravel Vite plugin.

---

## Основные сценарии

**Аккаунт**

- Зарегистрироваться по email/паролю или через GitHub OAuth
- Войти — получить Bearer JWT, который Laravel прокидывает в мета-тег страницы

**Доска (`/board`)**

- Создать задачу в любой колонке (Backlog / Today / In Process)
- Перетащить карточку между колонками — порядок и статус сохраняются
- Отметить дедлайн — карточка подсветится: жёлтый (≤3 дня) → янтарный (сегодня) → красный (просрочено)
- Открыть проект → добавить подзадачи → вывести подзадачу на доску одной кнопкой
- Логировать время кнопкой `+` на карточке — Redis обновляется сразу, до завершения
- Завершить задачу через кружок-кнопку — открывается модалка рефлексии (время + мысли)
- Проект нельзя завершить пока не выполнены все подзадачи
- Быстрая запись (FAB) — зафиксировать сделанное или потраченное время без задачи; по умолчанию непродуктивная
- Все изменения появляются во всех открытых вкладках мгновенно (WebSocket)

**Сделанное (`/done`)**

- Просмотреть выполненные задачи с фильтрами: поиск по тексту и `#тегам`, категория, период (Today / 7 / 30 дней / All Time), продуктивность
- Удалить запись через правую кнопку мыши
- Открыть карточку — просмотреть рефлексию и подзадачи проекта

**Аналитика (`/analytics`)**

- Обзорные метрики: кол-во задач, суммарное время, streak, completion rate
- Тепловая карта активности по дням (GitHub-style)
- Распределение по категориям и облако тегов
- Live-блок: активность за последние 24 часа из Redis

---

## Структура БД

```
users
  └─ id, email, password_hash

categories
  └─ id, user_id → users, name, color

activities
  └─ id, user_id → users
  └─ parent_id → activities   (подзадача принадлежит проекту)
  └─ category_id → categories (nullable)
  └─ category_snapshot_name / category_snapshot_color  (сохраняется при удалении категории)
  └─ title, description, reflection_text
  └─ status: backlog | today | in_process | done
  └─ is_project, is_on_board, is_quick_capture, is_productive  (флаги)
  └─ deadline, completed_at, time_spent_minutes, time_logged_minutes
  └─ tags (JSON), position
```

**Флаги активностей:**

| Тип                | is_project | parent_id | is_on_board | is_quick_capture |
| ------------------ | :--------: | :-------: | :---------: | :--------------: |
| Обычная задача     |     0      |   null    |      0      |        0         |
| Проект             |     1      |   null    |      0      |        0         |
| Подзадача          |     0      |    id     |      0      |        0         |
| Подзадача на доске |     0      |    id     |      1      |        0         |
| Быстрая запись     |     0      |   null    |      0      |        1         |

**Redis:** ключи `stats:user:{id}:daily:{date}:category:{category_id}` — счётчики минут для live-аналитики. Канал `board:{user_id}` — WebSocket события.

---

## Стек

| Слой           | Технологии                              |
| -------------- | --------------------------------------- |
| Frontend       | Alpine.js, Vite, Blade-шаблоны          |
| Backend (web)  | Laravel 11, PHP-FPM                     |
| Backend (API)  | FastAPI, SQLAlchemy (async), Alembic    |
| БД             | MySQL 8, Redis 7                        |
| Инфраструктура | Docker Compose, Nginx                   |
| Auth           | Laravel sessions + Bearer JWT → FastAPI |

---

## Гайд по продакшену

### .env

#### В корне

В `MYSQL_PASSWORD` ставим хороший пароль
В `MYSQL_ROOT_PASSWORD` ставим пароль ещё лучше
В `GHCR_OWNER` пишем ник в GitHub

#### В laravel

`APP_URL` и `API_URL` меняем на `https://reflectboard.emrysdev.xyz` и `https://reflectboard-api.emrysdev.xyz` соответственно

`APP_ENV` меняем на `production` и `APP_DEBUG` на `false`

В `DB_PASSWORD` ставим ранее указанный пароль (`MYSQL_PASSWORD`)

`GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` берутся отсюда:

```bash
https://github.com/settings/developers
```

В `GITHUB_REDIRECT_URI` ставим `https://reflectboard.emrysdev.xyz/auth/github/callback`

#### В fastapi

В `DATABASE_URL` ставим ранее указанный пароль (`MYSQL_PASSWORD`)

### deploy.yml

Так как сервер на архитектуре arm64, для сборки используется `ubuntu-24.04-arm`

Для сервера на x86_64 нужно заменить на закомментированный текст

Подключение по SSH происходит через Tailscale (подробнее про это далее).

### Секреты Github Actions

#### GHCR_TOKEN

```bash
https://github.com/settings/tokens
# галочка на write:packages
```

#### SERVER_SSH_KEY

```bash
ssh-keygen -t ed25519 -C "github-actions" -f ~/.ssh/github_actions -N ""
```

Создаст два файла:

- `~/.ssh/github_actions` — приватный ключ → идёт в `SERVER_SSH_KEY`
- `~/.ssh/github_actions.pub` — публичный ключ → копируем на сервер в `.ssh/authorized_keys`

#### SERVER_USER

Название говорит само за себя.

#### TAILSCALE_AUTHKEY

Я использую Tailscale для SSH-подключения, но если на сервере белый IP, можно напрямую. Тогда нужно менять `deploy.yml`.

```bash
https://login.tailscale.com/admin/settings/keys
# галочка на reusable
```

#### TAILSCALE_SERVER_HOST

IP в сети Tailscale

### На сервере

1. Создаём папки:

```bash
mkdir ~/reflectboard
mkdir -p ~/reflectboard/fastapi
mkdir -p ~/reflectboard/laravel
mkdir -p ~/reflectboard/nginx/conf.d
mkdir -p ~/reflectboard/mysql-conf
```

2. Копируем `.env` файлы в соответствующие папки.

3. Копируем `docker-compose.prod.yaml`.

4. Копируем prod-версию nginx-конфига (`nginx/conf.d/prod.conf.disable`) и переименовываем в `default.conf`.

5. Копируем `mysql-conf/reflectboard.cnf` — настройки MySQL (buffer pool, max_connections и т.д.) под лимит контейнера 768M.

6. Логинимся в GHCR вручную (только один раз):

```bash
echo ВАШ_GHCR_TOKEN | docker login ghcr.io -u ВАШ_GITHUB_USERNAME --password-stdin
```

7. Делаем запуск вручную (только в первый раз):

```bash
cd ~/reflectboard
docker compose -f docker-compose.prod.yaml pull
docker compose -f docker-compose.prod.yaml up -d
```

### Доступ к БД с локального ПК

В `docker-compose.prod.yaml` БД проброшена на `127.0.0.1:13306` — снаружи не торчит, доступ только через SSH-туннель. Нестандартный порт чтобы не конфликтовать с другими MySQL на сервере (host 3306 у меня уже занят).
