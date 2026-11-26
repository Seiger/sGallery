---
id: optimization
title: Image optimization
slug: /manipulations/optimization/
sidebar_position: 4
---

# Image optimization

Builder optimizes derivatives by default after resizing or cropping.

Slow uncached transformations can be handed to the optional sTask worker while the
frontend serves the original. See [background image optimization](../background-image-optimization.md)
for the cooperative time budget, heartbeat requirements and cache recovery.

```php
$url = sGallery::file($item->src)
    ->fit('crop', 592, 320)
    ->format('webp')
    ->quality(85)
    ->sharpen(10)
    ->getFile();
```

Use `optimize(false)` to disable optimization for the current image.

```php
$image = sGallery::file($item->src)
    ->optimize(false)
    ->fit('crop', 592, 320);
```

Use `sharpen()` when a resized or cropped image needs a small clarity boost.
The accepted value is from `0` to `100`.

```php
$image = sGallery::file($item->src)
    ->fit('crop', 592, 320)
    ->sharpen(10);
```

Use `quality()` for a fixed quality. Builder can select WebP quality automatically when a value is omitted. Quality, sharpening and optimization affect the cache key.
