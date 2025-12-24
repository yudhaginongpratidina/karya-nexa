<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<h1 align="center">KARYA NEXA APP</h1>

**KaryaNexa** is a modern, technology-driven web application designed to evaluate and rank employee performance objectively using the **TOPSIS (Technique for Order Preference by Similarity to Ideal Solution)** method. Built with the **Laravel framework**, KaryaNexa provides a structured, transparent, and scalable solution for organizations seeking data-driven performance appraisal systems.

## SETUP

### PHP.ini Config

```
extension=fileinfo
extension=zip
extension=pdo_mysql
```

### Environment

```env
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=karya_nexa_app
DB_USERNAME=root
DB_PASSWORD=root
```

### Commands

```bash
composer update             # update composer and package
composer install            # install package
```

```bash
php artisan key:generate    # generate key
php artisan migrate         # migration database
php artisan serve           # running live development
```

```bash
npm run dev                 # development mode
npm run build               # production mode
```