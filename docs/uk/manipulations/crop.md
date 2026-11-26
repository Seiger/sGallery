---
id: crop
title: Обрізання
slug: /manipulations/crop/
sidebar_position: 3
---

# Обрізання

- `crop($position, $width, $height)` вирізає ділянку в заданій позиції. Позиції: `topLeft`, `top`, `topRight`, `left`, `center`, `right`, `bottomLeft`, `bottom`, `bottomRight`.
- `focalCrop($x, $y, $width, $height)` намагається розмістити задану точку в центрі кадру. Біля краю зсуває рамку всередину зображення.
- `manualCrop($x, $y, $width, $height)` вирізає ділянку від її верхнього лівого кута. Нативний драйвер обрізає ділянку за межами правого або нижнього краю. Якщо початкова точка поза зображенням, повертає оригінал.

Координати відраховуються в пікселях від верхнього лівого кута зображення. Пропущена висота дорівнює ширині.

```php
$url = sGallery::file($item->src)->focalCrop(50, 50, 250, 200)->getFile();
```
