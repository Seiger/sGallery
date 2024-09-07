---
layout: page
title: Resize
description: sGallery Resize Image
permalink: /manipulations/resize/
---

# Resize

If you want to resize both height and width at the same time you can use the resize method.

```php
$image = sGallery::file(string $pathToImage)->resize(250, 200);
```

If you want a square image, you can specify only one width parameter.

```php
$image = sGallery::file($gallery->path)->resize(250);
```
