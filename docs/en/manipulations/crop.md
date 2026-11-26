---
id: crop
title: Crop
slug: /manipulations/crop/
sidebar_position: 3
---

# Crop

## Crop

By calling the crop method part of the image will be cropped to the given $width and $height dimensions (pixels).
Use `$position` to choose the crop location.

### Parameters:
- **`$position`** (string): The method of adjusting the image to the dimensions. specify which part will be cropped.
Available options are `'topLeft'`, `'top'`, `'topRight'`, `'left'`, `'center'`, `'right'`, `'bottomLeft'`, `'bottom'`,
and `'bottomRight'`.
- **`$width`** (int): The target width of the image in pixels.
- **`$height`** (int|null) The crop height in pixels. It defaults to `$width`.

```php
$image = sGallery::file($item->src)->crop('center', 250, 200);
```
---

## Focal crop

`focalCrop()` centers the crop on a point measured in source pixels from the top-left corner.
Near an edge it shifts the rectangle inside the image. The requested dimensions are limited
to the source size.

### Parameters:
- **`$centerX`** (int) The horizontal position of the focal point in source pixels.
- **`$centerY`** (int) The vertical position of the focal point in source pixels.
- **`$width`** (int): The target width of the image in pixels.
- **`$height`** (int|null) The crop height in pixels. It defaults to `$width`.

```php
$url = sGallery::file($item->src)->focalCrop(50, 50, 250, 200)->getFile();
```
---

## Manual crop

The manualCrop method crops a specific area of the image by specifying the **`$startX`** and **`$startY`**
positions and the crop's **`$width`** and **`$height`** in pixels.
The native path trims a crop that extends past the right or bottom edge and returns the original
image if the starting point is outside the source.

### Parameters:
- **`$startX`** (int): The start X position in pixels.
- **`$startY`** (int): The start Y position in pixels.
- **`$width`** (int): The target width of the image in pixels.
- **`$height`** (int|null) The crop height in pixels. It defaults to `$width`.

```php
$image = sGallery::file($item->src)->manualCrop(50, 50, 250, 200);
```
---
