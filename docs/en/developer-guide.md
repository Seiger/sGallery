---
id: developer-guide
title: Developer guide
sidebar_position: 6
---

# Developer guide

## Read a gallery

`collections()` returns a Builder. Use `itemType()`, `documentId()` and `blockName()` to choose the parent and gallery. Use `lang()` when the template needs a specific locale.

```php
$items = sGallery::collections()
    ->itemType('product')
    ->documentId((int) $product->id)
    ->blockName('slider')
    ->lang('en')
    ->get();
```

Use [escaped Blade output](./use-in-blade.md) for image URLs, alternative text and links. Use `sGallery::hasImage($item->type)` to distinguish images from download and video entries.

## Integrate a custom parent type

Assign a stable item type and block to the Manager controls. Register an [item policy](./configuration.md#manager-gallery-permissions) that authorizes the requested action and checks access to the specific parent. The same item type, parent ID and block identify the gallery on the frontend.

Render the package's Manager view from your module template.

```blade
{!! sGallery::initialiseView()->viewType('section')->itemType('product')->idType('i')->blockName('photo') !!}
```

Use `idType()` for the module's parent-ID request field. The available views are `tab`, `section` and `sectionDownloads`. An optional integration can check `evo()->bound('sGallery')` before rendering the view.

## Generate an image

```php
$url = sGallery::file($item->src)
    ->fit('max', 800, 600)
    ->format('webp')
    ->getFile();
```

Builder selects GD or Imagick based on the source and output formats. Use the [image manipulation guides](./manipulations/index.md) for geometry, output and quality settings. For slower uncached transforms, see [background image optimization](./background-image-optimization.md).
