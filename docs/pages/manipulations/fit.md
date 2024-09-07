---
layout: page
title: Fit
description: sGallery Fit Image
permalink: /manipulations/fit/
---

The `fit` method resizes an image to fit within the given `$width` and `$height` dimensions (in pixels)
using a specified `$fitMethod`. This allows for flexible resizing options depending on how the image should
fit within the constraints.

The `fit` method in `sGallery` offers various ways to handle image resizing, making it adaptable to different
layout and display needs. Depending on how the image should fit into the given space, you can choose from the
different fit methods to achieve the desired effect.

```php
$image = (string)sGallery::file(string $pathToImage)->fit(string $fitMethod, int $width, int $height);
```
or
```php
$image = sGallery::file(string $pathToImage)->fit(string $fitMethod, int $width, int $height)->__toString();
```
or
```php
$image = sGallery::file(string $pathToImage)->fit(string $fitMethod, int $width, int $height)->getFile();
```

## Parameters:
- **`$fitMethod`** (string): The method used to fit the image within the dimensions. Available options
are `'contain'`, `'max'`, `'fill'`, `'stretch'`, `'crop'`, and `'fill-max'`.
- **`$width`** (int): The target width of the image in pixels.
- **`$height`** (int|null): The target height of the image in pixels. If null, the height will be
proportional to the width.
---

## Available Fit Methods:

### 1. `'contain'`
Resizes the image to fit within the given width and height without cropping, distorting, or altering the
aspect ratio. The resulting image will be entirely visible within the boundaries.

```php
$image = (string)sGallery::file('path/to/image.jpg')->fit('contain', 500, 300);
```

### 2. `'max'`
Similar to `'contain'`, but the image will not be upscaled if it's smaller than the given dimensions.

```php
$image = (string)sGallery::file($gallery->path)->fit('max', 500, 300);
```

### 3. `'fill'`
Resizes the image to fit within the dimensions and fills any remaining space with a background color to match
the specified size.

```php
$image = (string)sGallery::file($gallery->path)->fit('fill', 500, 300);
```

### 4. `'stretch'`
Stretches the image to exactly fill the width and height dimensions, disregarding the original aspect ratio.
This can distort the image.

```php
$image = (string)sGallery::file($gallery->path)->fit('stretch', 500, 300);
```

### 5. `'crop'`
Resizes and crops the image to fit the width and height. Excess parts of the image outside the dimensions are cropped.

```php
$image = (string)sGallery::file($gallery->path)->fit('crop', 500, 300);
```

### 6. `'fill-max'`
Resizes the image to fit within the width and height boundaries, upscaling the image if necessary. Any extra space will be filled with the background color.

```php
$image = (string)sGallery::file($gallery->path)->fit('fill-max', 500, 300);
```
---
