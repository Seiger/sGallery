---
id: fit
title: Fit
slug: /manipulations/fit/
sidebar_position: 2
---

# Fit

Call `fit($method, $width, $height)` to control how an image fits a box measured in pixels. If you omit the height, Builder uses the width for both dimensions.

```php
$url = sGallery::file($item->src)->fit('max', 500, 300)->getFile();
```

| Mode | Result |
| --- | --- |
| `contain` | Fit inside the box while preserving the aspect ratio. |
| `max` | Fit inside the box without enlarging a smaller image. |
| `fill` | Fit without enlarging, then pad to the requested canvas size. |
| `stretch` | Resize to the exact dimensions; the aspect ratio can change. |
| `crop` | Scale to cover the box and crop the overflow. |
| `fill-max` | Fit inside the box, allowing enlargement, and pad to the requested canvas size. |

Use [Crop](./crop.md) when you need a specific position or source-pixel coordinates.
