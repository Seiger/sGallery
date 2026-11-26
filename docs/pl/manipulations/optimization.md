---
id: optimization
title: Optymalizacja obrazów
slug: /manipulations/optimization/
sidebar_position: 4
---

# Optymalizacja obrazów

Builder domyślnie optymalizuje obrazy wynikowe po zmianie rozmiaru lub kadrowaniu. Jakość, ostrość i optymalizacja wpływają na klucz pamięci podręcznej.

```php
$url = sGallery::file($item->src)
    ->fit('crop', 592, 320)
    ->format('webp')
    ->quality(85)
    ->sharpen(10)
    ->getFile();
```

`quality()` ustawia stałą jakość. Jeśli nie wskażesz jej dla WebP, Builder może dobrać ją automatycznie. `sharpen()` przyjmuje wartość od 0 do 100. `optimize(false)` wyłącza optymalizację bieżącego obrazu.

Powolne przekształcenia może wykonać [worker sTask](../background-image-optimization.md).
