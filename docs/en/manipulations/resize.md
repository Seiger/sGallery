---
id: resize
title: Resize
slug: /manipulations/resize/
sidebar_position: 1
---

# Resize

Use `resize($width, $height)` to set two dimensions in pixels. The second argument defaults to the width.

```php
$url = sGallery::file($item->src)->resize(250, 200)->getFile();
$equalBounds = sGallery::file($item->src)->resize(250)->getFile();
```

Use [fit](./fit.md) when you need to fit an image inside a box, pad the canvas or crop to a specific ratio.
