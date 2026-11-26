---
id: optimization
title: Optimisation des images
slug: /manipulations/optimization/
sidebar_position: 4
---

# Optimisation des images

Le Builder optimise par défaut les dérivés après un redimensionnement ou un recadrage. La qualité, la netteté et l’optimisation influencent la clé de cache.

```php
$url = sGallery::file($item->src)
    ->fit('crop', 592, 320)
    ->format('webp')
    ->quality(85)
    ->sharpen(10)
    ->getFile();
```

`quality()` fixe la qualité. Pour WebP, le Builder peut ajuster la qualité automatiquement si elle n’est pas spécifiée. `sharpen()` accepte une valeur de 0 à 100. `optimize(false)` désactive l’optimisation pour l’image traitée.

Le [worker sTask](../background-image-optimization.md) peut prendre en charge les transformations lentes.
