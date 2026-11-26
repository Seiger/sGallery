---
id: optimization
title: Оптимізація зображень
slug: /manipulations/optimization/
sidebar_position: 4
---

# Оптимізація зображень

Builder типово оптимізує похідні зображення після зміни розмірів або обрізання. Параметри якості, різкості й оптимізації впливають на ключ кешу.

```php
$url = sGallery::file($item->src)
    ->fit('crop', 592, 320)
    ->format('webp')
    ->quality(85)
    ->sharpen(10)
    ->getFile();
```

`quality()` задає фіксовану якість. Для WebP без явної якості можливий адаптивний підбір. `sharpen()` приймає значення від 0 до 100. `optimize(false)` вимикає оптимізацію для поточного зображення.

Повільні перетворення може виконати [воркер sTask](../background-image-optimization.md).
