# Reservation Site

Laravel 12 + PHP 8.5 + MySQL 8.4 + Node.js 24 + Tailwind CSS 4 development environment.

## URLs

- App: http://localhost
- Vite: http://localhost:5173
- MySQL: 127.0.0.1:3306

## Start

```powershell
docker compose up -d --build
```

## Stop

```powershell
docker compose down
```

## Useful commands

```powershell
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose exec app composer install
docker compose run --rm vite npm install
docker compose logs -f
```
