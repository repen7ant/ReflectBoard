# Маршрутизация и API

## Laravel — страницы и роуты

| Роут         | Описание                           |
| ------------ | ---------------------------------- |
| `/`          | Лендинг или редирект на `/board`   |
| `/board`     | Kanban-доска (SSR + JS → FastAPI)  |
| `/done`      | Журнал выполненных задач           |
| `/analytics` | Дашборд статистики (Redis + MySQL) |
| `/auth/...`  | Регистрация / вход (сессии)        |

## FastAPI — эндпоинты

| Метод  | Путь                  | Описание                                      |
| ------ | --------------------- | --------------------------------------------- |
| GET    | `/activities`         | Список (фильтры: status, date, category)      |
| POST   | `/activities`         | Создать задачу / проект / быструю запись      |
| PATCH  | `/activities/{id}`    | Редактировать задачу (Dual-Write + WS push)   |
| DELETE | `/activities/{id}`    | Удалить запись                                |
| POST   | `/activities/reorder` | Изменить порядок карточек в колонке           |
| GET    | `/activities/done`    | Выполненные (фильтры: search, category, date) |
| GET    | `/categories`         | Список категорий                              |
| POST   | `/categories`         | Создать категорию (WS push `category_create`) |
| DELETE | `/categories/{id}`    | Удалить категорию (snapshot + WS `category_delete`) |
| WS     | `/api/v1/ws`          | WebSocket real-time события доски             |

> Аутентификация WebSocket: после подключения клиент первым сообщением шлёт `{"token": "<api_token>"}` (тот же Bearer-токен из `users.api_token`, не JWT). Токен в query-параметре не передаётся.
