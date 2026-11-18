---
layout: page
title: Crop
description: sGallery Crop Image
permalink: /manipulations/crop/
---

## Crop

By calling the crop method part of the image will be cropped to the given $width and $height dimensions (pixels).
Use the $cropMethod to specify which part will be cropped out.

```php
$image = sGallery::file(string $pathToImage)->crop(string $position, int $width, int $height);
```

### Parameters:
- **`$position`** (string): The method of adjusting the image to the dimensions. specify which part will be cropped.
Available options are `'topLeft'`, `'top'`, `'topRight'`, `'left'`, `'center'`, `'right'`, `'bottomLeft'`, `'bottom'`,
and `'bottomRight'`.
- **`$width`** (int): The target width of the image in pixels.
- **`$height`** (int|null) The crop height in pixels. It defaults to `$width`.

```php
$image = sGallery::file($gallery->path)->crop('center', 250, 200);
```
---

## Focal crop

`focalCrop()` centers the crop on a point measured in source pixels from the top-left corner.
Near an edge it shifts the rectangle inside the image. The requested dimensions are limited
to the source size.

```php
$image = sGallery::file(string $pathToImage)->focalCrop(int $centerX, int $centerY, int $width, int $height);
```

### Parameters:
- **`$centerX`** (int) The horizontal position of the focal point in source pixels.
- **`$centerY`** (int) The vertical position of the focal point in source pixels.
- **`$width`** (int): The target width of the image in pixels.
- **`$height`** (int|null) The crop height in pixels. It defaults to `$width`.

```php
$image = sGallery::file($gallery->path)->focalCrop(50, 50, 250, 200);
```
---

## Manual crop

The manualCrop method crops a specific area of the image by specifying the **`$startX`** and **`$startY`**
positions and the crop's **`$width`** and **`$height`** in pixels.
The native path trims a crop that extends past the right or bottom edge and returns the original
image if the starting point is outside the source.

```php
$image = sGallery::file(string $pathToImage)->manualCrop(int $startX, int $startY, int $width, int $height);
```

### Parameters:
- **`$startX`** (int): The start X position in pixels.
- **`$startY`** (int): The start Y position in pixels.
- **`$width`** (int): The target width of the image in pixels.
- **`$height`** (int|null) The crop height in pixels. It defaults to `$width`.

```php
$image = sGallery::file($gallery->path)->manualCrop(50, 50, 250, 200);
```
---
