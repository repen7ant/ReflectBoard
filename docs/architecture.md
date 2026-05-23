# Архитектура и База данных (MySQL)

> UUID хранятся как `CHAR(36)`. Поле `tags` — JSON-колонка.

## Схема БД

### Таблица `users`

| Поле                        | Тип                          | Описание   |
| --------------------------- | ---------------------------- | ---------- |
| `id`                        | CHAR(36) PK                  | UUID       |
| `email`                     | VARCHAR(255) UNIQUE NOT NULL | Email      |
| `password_hash`             | VARCHAR(255) NOT NULL        | Хэш пароля |
| `created_at` / `updated_at` | TIMESTAMP                    |            |

### Таблица `categories`

| Поле         | Тип                    | Описание |
| ------------ | ---------------------- | -------- |
| `id`         | CHAR(36) PK            | UUID     |
| `user_id`    | CHAR(36) FK → users.id | Владелец |
| `name`       | VARCHAR(255) NOT NULL  | Название |
| `color`      | VARCHAR(7) NOT NULL    | HEX-цвет |
| `created_at` | TIMESTAMP              |          |

### Таблица `activities`

| Поле                      | Тип                   | Описание                                          |
| ------------------------- | --------------------- | ------------------------------------------------- |
| `id`                      | CHAR(36) PK           | UUID                                              |
| `user_id`                 | CHAR(36) FK NOT NULL  | Владелец                                          |
| `parent_id`               | CHAR(36) nullable     | Ссылка на проект                                  |
| `category_id`             | CHAR(36) nullable     | Категория                                         |
| `category_snapshot_name`  | VARCHAR(255) nullable | Snapshot имени категории при удалении             |
| `category_snapshot_color` | VARCHAR(7) nullable   | Snapshot цвета категории при удалении             |
| `title`                   | VARCHAR(255) NOT NULL | Заголовок                                         |
| `description`             | TEXT nullable         | Описание                                          |
| `reflection_text`         | TEXT nullable         | Рефлексия                                         |
| `time_spent_minutes`      | INT nullable          | Затраченное время                                 |
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

## Механизм синхронизации (Dual-Write)

При завершении задачи или создании быстрой записи FastAPI выполняет:

1. **Персистентность** — запись в MySQL
2. **Агрегация** — инкремент счётчиков в Redis по ключам `stats:user:{id}:daily:{date}:category:{category_id}`
3. **Уведомление доски** — публикация события в Redis-канал `board:{user_id}` → WebSocket push
