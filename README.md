# Гайд по продакшену

## .env

### В корне

В MYSQL_PASSWORD ставим хороший пароль
В MYSQL_ROOT_PASSWORD ставим пароль еще лучше
В GHCR_OWNER пишем ник в github

### В laravel

APP_URL и API_URL меняем на https://reflectboard.emrysdev.xyz и https://reflectboard-api.emrysdev.xyz соответственно

APP_ENV меняем production и APP_DEBUG на false

В DB_PASSWORD ставим ранее указанный пароль (MYSQL_PASSWORD)

GITHUB_CLIENT_ID, GITHUB_CLIENT_SECRET берутся отсюда:

```bash
https://github.com/settings/developers
```

В GITHUB_REDIRECT_URI ставим https://reflectboard.emrysdev.xyz/auth/github/callback

### В fastapi

В DATABASE_URL ставим ранее указанный пароль (MYSQL_PASSWORD)

## deploy.yml

Так как сервер на архитектуре arm64, то для сборки используется ubuntu-24.04-arm

Для сервера на x86_64 нужно заменить на закомментированный текст

Подключение по ssh происходит через tailscale (подробнее про это далее).

## Секреты Github Actions

### GHCR_TOKEN

```bash
https://github.com/settings/tokens
# галочка на write:packages
```

### SERVER_SSH_KEY

```bash
ssh-keygen -t ed25519 -C "github-actions" -f ~/.ssh/github_actions -N ""
```

Создаст два файла:

- ~/.ssh/github_actions — приватный ключ -> идёт в SERVER_SSH_KEY
- ~/.ssh/github_actions.pub — публичный ключ -> копируем на сервер в .ssh/authorized_keys

### SERVER_USER

Название говорит само за себя.

### TAILSCALE_AUTHKEY

Я использую tailscale для ssh-подключения, но если на сервере белый IP, то можно напрямую. Но тогда нужно менять deploy.yml.

```bash
https://login.tailscale.com/admin/settings/keys
# галочка на reusable
```

### TAILSCALE_SERVER_HOST

IP в сети tailscale

## На сервере

1. Создаем папки:

```bash
mkdir ~/reflectboard
mkdir -p ~/reflectboard/fastapi
mkdir -p ~/reflectboard/laravel
mkdir -p ~/reflectboard/nginx/conf.d
```

2. Копируем .env файлы в соответсвующие папки.

3. Копируем docker-compose.prod.yaml.

4. Копируем prod-версию nginx-конфига (nginx/conf.d/prod.conf.disable) и переименовываем в default.conf.

5. Логинимся в GHCR вручную (только один раз):

```bash
echo ВАШ_GHCR_TOKEN | docker login ghcr.io -u ВАШ_GITHUB_USERNAME --password-stdin
```

6. Делаем запуск вручную (только в первый раз):

```bash
cd ~/reflectboard
docker compose -f docker-compose.prod.yaml pull
docker compose -f docker-compose.prod.yaml up -d
```
