---
id: crop
title: Zuschneiden
slug: /manipulations/crop/
sidebar_position: 3
---

# Zuschneiden

- Mit `crop($position, $width, $height)` wählen Sie einen Ausschnitt. Die Positionen sind `topLeft`, `top`, `topRight`, `left`, `center`, `right`, `bottomLeft`, `bottom`, `bottomRight`.
- `focalCrop($x, $y, $width, $height)` zentriert den Ausschnitt auf einem Bildpunkt und verschiebt ihn am Rand nach innen.
- `manualCrop($x, $y, $width, $height)` setzt die linke obere Ecke. Die native Verarbeitung begrenzt Überstände und gibt bei einem Ausgangspunkt außerhalb des Bildes das Original zurück.

Koordinaten werden in Quellpixeln von der linken oberen Ecke aus gemessen. Ohne Höhe wird die Breite verwendet.

```php
$url = sGallery::file($item->src)->focalCrop(50, 50, 250, 200)->getFile();
```
