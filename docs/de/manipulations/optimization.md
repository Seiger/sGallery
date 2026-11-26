---
id: optimization
title: Bildoptimierung
slug: /manipulations/optimization/
sidebar_position: 4
---

# Bildoptimierung

Builder optimiert abgeleitete Bilder standardmäßig nach einer Größenänderung oder einem Zuschnitt. Qualität, Schärfe und Optimierung wirken sich auf den Cache-Schlüssel aus.

```php
$url = sGallery::file($item->src)
    ->fit('crop', 592, 320)
    ->format('webp')
    ->quality(85)
    ->sharpen(10)
    ->getFile();
```

`quality()` legt eine feste Qualität fest. Ohne expliziten Wert kann Builder die WebP-Qualität automatisch wählen. `sharpen()` akzeptiert Werte von 0 bis 100. `optimize(false)` deaktiviert die Optimierung für das aktuelle Bild.

Langsame Verarbeitungen kann der [sTask-Worker](../background-image-optimization.md) übernehmen.
