---
id: getting-started
title: Початок роботи
slug: /getting-started/
sidebar_position: 2
---

# Початок роботи

## Встановлення в Evolution CMS

Для sGallery 2.x потрібні PHP від 8.4 у серії 8.x, Evolution CMS від 3.6 у серії 3.x і Composer. Для мініатюр встановіть GD або Imagick. sTask підключається за потреби.

Виконайте в корені проєкту Evolution:

```bash
cd core
php artisan package:installrequire seiger/sgallery "^2.0"
php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"
php artisan migrate
```

## Перша галерея

Відкрийте **Конфігурація системи → sGallery**, додайте шаблон ресурсу й збережіть. Також можна додати ID шаблону до файла `core/custom/config/seiger/settings/sGallery.php`:

```php
<?php return [5];
```

Відкрийте ресурс із цим шаблоном і додайте фото. [Посібник редактора](./user-guide.md) пояснює роботу з медіа, а [приклад Blade](./use-in-blade.md) показує їх вивід на сайті.
