---
id: resize
title: Größe ändern
slug: /manipulations/resize/
sidebar_position: 1
---

# Größe ändern

`resize($width, $height)` nimmt zwei Werte in Pixeln entgegen. Ohne Höhenangabe verwendet der Builder die Breite für beide Werte. Mit [Einpassen](./fit.md) lässt sich das Bild in eine feste Fläche einpassen.

```php
$url = sGallery::file($item->src)->resize(250, 200)->getFile();
$equalBounds = sGallery::file($item->src)->resize(250)->getFile();
```
