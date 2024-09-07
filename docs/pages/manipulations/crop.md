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
- **`$height`** (int|null): The target height of the image in pixels. If null, the height will be
proportional to the width.

```php
$image = sGallery::file($gallery->path)->crop('center', 250, 200);
```
---

## Focal crop

The focalCrop method can be used to crop around an exact position. The center of the crop is controlled by the
**`$centerX`** and **`$centerY`** values in percent (0 - 100).

```php
$image = sGallery::file(string $pathToImage)->focalCrop(int $centerX, int $centerY, int $width, int $height);
```

### Parameters:
- **`$centerX`** (int): The center of the crop in percent (0 - 100).
- **`$centerY`** (int): The center of the crop in percent (0 - 100).
- **`$width`** (int): The target width of the image in pixels.
- **`$height`** (int|null): The target height of the image in pixels. If null, the height will be
proportional to the width.

```php
$image = sGallery::file($gallery->path)->focalCrop(50, 50, 250, 200);
```
---

## Manual crop

The manualCrop method crops a specific area of the image by specifying the **`$startX`** and **`$startY`**
positions and the crop's **`$width`** and **`$height`** in pixels.

```php
$image = sGallery::file(string $pathToImage)->manualCrop(int $startX, int $startY, int $width, int $height);
```

### Parameters:
- **`$startX`** (int): The start X position in pixels.
- **`$startY`** (int): The start Y position in pixels.
- **`$width`** (int): The target width of the image in pixels.
- **`$height`** (int|null): The target height of the image in pixels. If null, the height will be
  proportional to the width.

```php
$image = sGallery::file($gallery->path)->manualCrop(50, 50, 250, 200);
```
---
