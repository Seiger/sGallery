---
id: width-and-height
title: Width and height
slug: /manipulations/width-and-height/
sidebar_position: 5
---

# Width and height

Pass dimensions in pixels to `resize()`, `fit()` or `crop()`. When the height is omitted, these Builder methods use the supplied width for both dimensions. An aspect-preserving fit can still produce a non-square derivative.

```php
$url = sGallery::file($item->src)->fit('max', 500, 300)->getFile();
```

See [Resize](./resize.md) for explicit dimensions and [Fit](./fit.md) for aspect-ratio behavior.
