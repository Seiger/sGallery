---
id: optimization
title: Image optimization
slug: /manipulations/optimization/
sidebar_position: 4
---

# Image optimization

Processed images are optimized by default when they are rendered through `sGallery::file()`.
The optimization step runs after resizing, fitting, or cropping, so generated cache files stay as small as possible.

Slow uncached transformations can be handed to the optional sTask worker while the
frontend serves the original. See [background image optimization](../background-image-optimization.md)
for the cooperative time budget, heartbeat requirements and cache recovery.

```php
$image = sGallery::file($item->src)->fit('crop', 592, 320);
```

You can disable optimization for a specific image when the output should be left untouched.

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

Optimization and sharpening are included in the generated cache file name, so changing these options creates a separate cached derivative.
