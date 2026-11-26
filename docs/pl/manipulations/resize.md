---
id: resize
title: Zmiana rozmiaru
slug: /manipulations/resize/
sidebar_position: 1
---

# Zmiana rozmiaru

`resize($width, $height)` przyjmuje wymiary w pikselach. Jeśli pominiesz wysokość, Builder użyje wartości szerokości.

```php
$url = sGallery::file($item->src)->resize(250, 200)->getFile();
$equalBounds = sGallery::file($item->src)->resize(250)->getFile();
```

Aby dopasować obraz do ramki lub wykadrować go według proporcji, użyj [fit](./fit.md).
