# E-IMZO Laravel Authentication Demo

Laravel loyihasi E-IMZO elektron raqamli imzo orqali autentifikatsiya va hujjatlarni imzolash.

## Talablar

- PHP 8.1+
- Composer
- E-IMZO dasturi (https://e-imzo.uz) - kompyuteringizda o'rnatilgan bo'lishi kerak
- E-IMZO Server (ixtiyoriy - imzoni tekshirish uchun)

## O'rnatish

```bash
cd e-imzo-app
composer install
php artisan migrate
php artisan db:seed
```

## Ishga tushirish

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Brauzerda ochish: http://127.0.0.1:8000

## Funksiyalar

### 1. E-IMZO bilan kirish
- ERI kalitini tanlash (PFX fayl yoki USB token)
- Challenge imzolash orqali autentifikatsiya
- Avtomatik ro'yxatdan o'tish (yangi foydalanuvchi)

### 2. Hujjatlar
- Yangi hujjat yaratish
- Hujjatni E-IMZO bilan imzolash
- QR kod orqali hujjatni tekshirish

### 3. QR kod tekshirish
- Har bir hujjatga noyob QR kod
- QR kodni skanerlash orqali hujjat ma'lumotlarini ko'rish
- Imzo ma'lumotlarini tekshirish

## E-IMZO Server

Imzoni to'liq tekshirish uchun E-IMZO Server kerak:

```bash
java -Dfile.encoding=UTF-8 -jar e-imzo-server.jar config.properties
```

Server http://127.0.0.1:8080 da ishlaydi.

## Konfiguratsiya

`.env` faylida:

```
EIMZO_SERVER_URL=http://127.0.0.1:8080
```

## API Endpoints

| Method | URL | Tavsif |
|--------|-----|--------|
| GET | /login | Kirish sahifasi |
| GET | /frontend/challenge | Challenge olish |
| POST | /eimzo/authenticate | E-IMZO autentifikatsiya |
| GET | /documents | Hujjatlar ro'yxati |
| POST | /documents | Yangi hujjat |
| POST | /documents/{id}/sign | Hujjatni imzolash |
| GET | /verify/{qrCode} | QR kod tekshirish |

## Texnologiyalar

- Laravel 10
- Bootstrap 5
- E-IMZO JavaScript API
- SQLite (development)
- QR Code Generator

## IMPORT CSV
```
# Recommended — bypass PHP CLI memory limit entirely:
php -d memory_limit=512M artisan transactions:import --fresh

# Or re-seed via seeder:
php -d memory_limit=512M artisan db:seed --class=TransactionsSeeder
```
