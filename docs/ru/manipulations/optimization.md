---
id: optimization
title: Оптимизация изображений
slug: /manipulations/optimization/
sidebar_position: 4
---

# Оптимизация изображений

Builder оптимизирует производные изображения по умолчанию. `quality()` задаёт фиксированное качество. Для WebP без явного значения возможен адаптивный подбор. `sharpen()` принимает 0–100. `optimize(false)` выключает оптимизацию для этого изображения.

```php
$url = sGallery::file($item->src)
    ->fit('crop', 592, 320)
    ->format('webp')
    ->quality(85)
    ->sharpen(10)
    ->getFile();
```

- [Оптимизация изображений в фоне](../background-image-optimization.md)
