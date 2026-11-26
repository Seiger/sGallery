---
id: getting-started
title: Начало работы
slug: /getting-started/
sidebar_position: 2
---

# Начало работы

Для sGallery 1.9 нужны PHP от 8.4 в серии 8.x, Evolution CMS от 3.5 в серии 3.x и Composer. Для генерации изображений установите GD или Imagick. Чистая установка на PHP 8.6 проверена на [Evolution CMS 3.5.x](https://github.com/evolution-cms/evolution/commit/ea42b18c8bbadbcb9b994c8f8cb61e824398bb17). Для фоновой обработки на Evolution CMS 3.5.9 используйте [sTask 2.3.2](https://github.com/Seiger/sTask/releases/tag/v2.3.2) с исправлением повторной миграции.

Выполните из корня проекта:

```bash
cd core
php artisan package:installrequire seiger/sgallery "^1.9"
php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"
php artisan migrate
```

В системной конфигурации откройте **sGallery**, назначьте шаблон и сохраните. Откройте ресурс с этим шаблоном и добавьте изображение.
