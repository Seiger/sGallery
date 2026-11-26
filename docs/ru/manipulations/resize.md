---
id: resize
title: Изменение размера
slug: /manipulations/resize/
sidebar_position: 1
---

# Изменение размера

`resize($width, $height)` принимает размеры в пикселях. Если высота пропущена, Builder использует ширину для обоих значений. Для сохранения пропорций и заполнения полей используйте [подгонку](./fit.md).

```php
$url = sGallery::file($item->src)->resize(250, 200)->getFile();
$equalBounds = sGallery::file($item->src)->resize(250)->getFile();
```
