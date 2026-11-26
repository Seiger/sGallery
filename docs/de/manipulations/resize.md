---
id: resize
title: Größe ändern
slug: /manipulations/resize/
sidebar_position: 1
---

# Größe ändern

`resize($width, $height)` erwartet die Abmessungen in Pixeln. Ohne Höhenangabe verwendet Builder den Wert der Breite.

```php
$url = sGallery::file($item->src)->resize(250, 200)->getFile();
$equalBounds = sGallery::file($item->src)->resize(250)->getFile();
```

Zum Einpassen in einen Rahmen oder zum proportionalen Zuschneiden verwenden Sie [fit](./fit.md).
