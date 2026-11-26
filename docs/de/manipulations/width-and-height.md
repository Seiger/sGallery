---
id: width-and-height
title: Breite und Höhe
slug: /manipulations/width-and-height/
sidebar_position: 5
---

# Breite und Höhe

`resize()`, `fit()` und `crop()` erwarten die Abmessungen in Pixeln. Ohne Höhenangabe sind Rahmenbreite und -höhe gleich. Die tatsächliche Größe des abgeleiteten Bildes hängt vom [Anpassungsmodus](./fit.md) und den Proportionen des Originals ab.

```php
$url = sGallery::file($item->src)->fit('max', 500, 300)->getFile();
```
