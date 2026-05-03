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
| POST   | `/categories`         | Создать категорию                             |
| DELETE | `/categories/{id}`    | Удалить категорию (с сохранением snapshot)    |
| WS     | `/ws?token={jwt}`     | WebSocket real-time события доски             |
