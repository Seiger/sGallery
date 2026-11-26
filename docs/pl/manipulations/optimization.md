---
id: optimization
title: Optymalizacja obrazów
slug: /manipulations/optimization/
sidebar_position: 4
---

# Optymalizacja obrazów

Builder domyślnie optymalizuje wyniki. `quality()` ustawia stałą jakość. WebP może korzystać z adaptacyjnego doboru, jeśli jakości nie podano. `sharpen()` przyjmuje 0–100. Wywołanie `optimize(false)` wyłącza optymalizację tego obrazu.

```php
$url = sGallery::file($item->src)
    ->fit('crop', 592, 320)
    ->format('webp')
    ->quality(85)
    ->sharpen(10)
    ->getFile();
```

- [Optymalizacja obrazów w tle](../background-image-optimization.md)
