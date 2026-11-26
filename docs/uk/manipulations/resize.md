---
id: resize
title: Зміна розміру
slug: /manipulations/resize/
sidebar_position: 1
---

# Зміна розміру

Метод `resize($width, $height)` приймає розміри в пікселях. Якщо висоту опущено, Builder використає значення ширини.

```php
$url = sGallery::file($item->src)->resize(250, 200)->getFile();
$equalBounds = sGallery::file($item->src)->resize(250)->getFile();
```

Для підгонки під рамку або обрізання за пропорціями використовуйте [fit](./fit.md).
