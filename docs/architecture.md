# Архитектура и База данных (MySQL)

> Первичные ключи — `BIGINT` (auto-increment). Поле `tags` — JSON-колонка. Время хранится в UTC.

## Схема БД

### Таблица `users`

| Поле                        | Тип                          | Описание                          |
| --------------------------- | ---------------------------- | --------------------------------- |
| `id`                        | BIGINT PK                    |                                   |
| `email`                     | VARCHAR(255) UNIQUE NOT NULL | Email                             |
| `password_hash`             | VARCHAR(255) NOT NULL        | Хэш пароля                        |
| `api_token`                 | VARCHAR(80) UNIQUE           | Bearer-токен для FastAPI          |
| `telegram_id`               | BIGINT UNIQUE nullable       | Привязанный Telegram-аккаунт      |
| `created_at` / `updated_at` | TIMESTAMP                    |                                   |

### Таблица `categories`

| Поле         | Тип                    | Описание |
| ------------ | ---------------------- | -------- |
| `id`         | BIGINT PK              | auto-increment |
| `user_id`    | BIGINT FK → users.id   | Владелец |
| `name`       | VARCHAR(255) NOT NULL  | Название |
| `color`      | VARCHAR(7) NOT NULL    | HEX-цвет |
| `created_at` | TIMESTAMP              |          |

### Таблица `activities`

| Поле                      | Тип                   | Описание                                          |
| ------------------------- | --------------------- | ------------------------------------------------- |
| `id`                      | BIGINT PK             | auto-increment                                    |
| `user_id`                 | BIGINT FK NOT NULL    | Владелец                                          |
| `parent_id`               | BIGINT nullable       | Ссылка на проект                                  |
| `category_id`             | BIGINT nullable       | Категория                                         |
| `category_snapshot_name`  | VARCHAR(255) nullable | Snapshot имени категории при удалении             |
| `category_snapshot_color` | VARCHAR(7) nullable   | Snapshot цвета категории при удалении             |
| `title`                   | VARCHAR(255) NOT NULL | Заголовок                                         |
| `description`             | TEXT nullable         | Описание                                          |
| `reflection_text`         | TEXT nullable         | Рефлексия                                         |
| `time_spent_minutes`      | INT nullable          | Суммарное затраченное время (минуты)              |
| `time_logged_minutes`     | INT DEFAULT 0         | Время добавленное через лог-кнопку (не дублируется при завершении) |
| `is_productive`           | TINYINT(1) DEFAULT 1  | Продуктивная активность (0 = непродуктивная)      |
| `status`                  | ENUM NOT NULL         | `backlog\|today\|in_process\|done`                |
| `is_project`              | TINYINT(1) DEFAULT 0  | Карточка является проектом                        |
| `is_on_board`             | TINYINT(1) DEFAULT 0  | Подзадача выведена на доску                       |
| `is_quick_capture`        | TINYINT(1) DEFAULT 0  | Быстрая запись (FAB)                              |
| `deadline`                | TIMESTAMP nullable    | Дедлайн                                           |
| `tags`                    | JSON DEFAULT ('[]')   | Массив тегов                                      |
| `completed_at`            | TIMESTAMP nullable    | Дата завершения                                   |
| `position`                | INT DEFAULT 0         | Позиция карточки внутри колонки для сортировки    |
| `created_at` / `updated_at` | TIMESTAMP           |                                                   |

#### Логика флагов

| Тип записи                 | `is_project` | `parent_id` | `is_on_board` | `is_quick_capture` |
| -------------------------- | ------------ | ----------- | ------------- | ------------------ |
| Обычная задача             | 0            | null        | 0             | 0                  |
| Проект                     | 1            | null        | 0             | 0                  |
| Подзадача (внутри проекта) | 0            | `<uuid>`    | 0             | 0                  |
| Подзадача на доске         | 0            | `<uuid>`    | 1             | 0                  |
| Быстрая запись (FAB)       | 0            | null        | 0             | 1                  |

### Таблица `user_bot_settings`

| Поле                  | Тип                   | Описание                                      |
| --------------------- | --------------------- | --------------------------------------------- |
| `user_id`             | BIGINT PK FK → users  |                                               |
| `deadline_lead_hours` | VARCHAR(50)           | Часы через запятую, напр. `"168,72,24"`       |
| `reminder_time`       | VARCHAR(5) nullable   | Время напоминания залогировать активности     |
| `today_reminder_time` | VARCHAR(5) nullable   | Время напоминания об активных задачах         |
| `tz_offset_minutes`   | SMALLINT              | UTC-смещение пользователя в минутах           |

---

## Real-time обновления (WebSocket)

FastAPI публикует события в Redis-канал `board:{user_id}` при любом изменении активностей или категорий. Все открытые вкладки получают обновление без перезагрузки.

Формат события: `{"action": <тип>, "data": <объект>}`. Типы `action`:

| action            | data                | Когда                                    |
| ----------------- | ------------------- | ---------------------------------------- |
| `create`          | активность          | Создана активность                       |
| `update`          | активность          | Изменена активность (в т.ч. log-time)    |
| `delete`          | `{id}`              | Удалена активность                       |
| `reorder`         | `{}`                | Изменён порядок карточек                  |
| `category_create` | категория           | Создана категория                        |
| `category_delete` | `{id}`              | Удалена категория                        |

Каждое сообщение доставляется всем подписчикам канала, включая вкладку-инициатор, поэтому клиент обрабатывает события идемпотентно (дедупликация по `id`).

**При логировании времени (`POST /activities/{id}/log-time`):**
1. `time_spent_minutes += minutes`, `time_logged_minutes += minutes` → MySQL
2. WebSocket push → все вкладки видят обновлённый бейдж

**При завершении задачи или создании быстрой записи:**
1. Запись результата → MySQL
2. WebSocket push → задача уходит с доски

**Live-аналитика** (последние 24 часа) читается напрямую из MySQL: задачи со статусом `done` и `completed_at >= now - 24h`.
