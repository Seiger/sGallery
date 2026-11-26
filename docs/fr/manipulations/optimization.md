---
id: optimization
title: Optimisation des images
slug: /manipulations/optimization/
sidebar_position: 4
---

# Optimisation des images

Le Builder optimise les dérivés par défaut. `quality()` fixe la qualité. En WebP, une sélection adaptative est possible sans valeur explicite. `sharpen()` accepte une valeur entre 0 et 100. `optimize(false)` désactive l’optimisation pour cet appel.

```php
$url = sGallery::file($item->src)
    ->fit('crop', 592, 320)
    ->format('webp')
    ->quality(85)
    ->sharpen(10)
    ->getFile();
```

- [Optimisation des images en arrière-plan](../background-image-optimization.md)
