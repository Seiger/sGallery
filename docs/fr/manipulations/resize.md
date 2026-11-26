---
id: resize
title: Redimensionnement
slug: /manipulations/resize/
sidebar_position: 1
---

# Redimensionnement

`resize($width, $height)` reçoit les dimensions en pixels. Si la hauteur est omise, le Builder utilise la largeur.

```php
$url = sGallery::file($item->src)->resize(250, 200)->getFile();
$equalBounds = sGallery::file($item->src)->resize(250)->getFile();
```

Pour ajuster l’image à un cadre ou la recadrer proportionnellement, utilisez [fit](./fit.md).
