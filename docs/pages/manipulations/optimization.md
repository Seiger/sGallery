---
layout: page
title: Image optimization
description: Optimize processed images and apply optional sharpening in sGallery.
permalink: /manipulations/optimization/
---

Processed images are optimized by default when they are rendered through `sGallery::file()`.
The optimization step runs after resizing, fitting, or cropping, so generated cache files stay as small as possible.

```php
$image = sGallery::file($gallery->path)->fit('crop', 592, 320);
```

You can disable optimization for a specific image when the output should be left untouched.

```php
$image = sGallery::file($gallery->path)
    ->optimize(false)
    ->fit('crop', 592, 320);
```

Use `sharpen()` when a resized or cropped image needs a small clarity boost.
The accepted value is from `0` to `100`.

```php
$image = sGallery::file($gallery->path)
    ->fit('crop', 592, 320)
    ->sharpen(10);
```

Optimization and sharpening are included in the generated cache file name, so changing these options creates a separate cached derivative.
