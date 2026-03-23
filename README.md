# KARYA NEXA

```bash
git clone https://github.com/yudhaginongpratidina/karya-nexa.git
```

## MODAL AND MIGRATION

```bash
php artisan make:model Category -m
php artisan make:model Criteria -m
php artisan make:model Period -m
php artisan make:model Performance -m
php artisan make:model TopsisResult -m
```

## OTHER COMMANDS

```bash
php artisan make:controller Api/<name>Controller
php artisan make:seeder <name>Seeder
php artisan db:seed --class=<name>Seeder
```

## HOW TO RUN

```bash
php artisan key:generate
php artisan serve
```

```
http://localhost:8000
```