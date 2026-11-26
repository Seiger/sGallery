---
id: optimization
title: Bildoptimierung
slug: /manipulations/optimization/
sidebar_position: 4
---

# Bildoptimierung

Der Builder optimiert erzeugte Bilder standardmäßig. `quality()` setzt eine feste Qualität. Bei WebP kann der Builder ohne feste Qualität einen geeigneten Wert wählen. `sharpen()` akzeptiert 0 bis 100. Mit `optimize(false)` deaktivieren Sie die Optimierung für diesen Aufruf.

```php
$url = sGallery::file($item->src)
    ->fit('crop', 592, 320)
    ->format('webp')
    ->quality(85)
    ->sharpen(10)
    ->getFile();
```

- [Bildoptimierung im Hintergrund](../background-image-optimization.md)
