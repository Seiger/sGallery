---
layout: page
title: Configuration
description: Configuration sGallery tabs
permalink: /configuration/
---

## Config file

The configuration file is located here:

```console
../core/custom/config/seiger/settings/sGallery.php
```

## Simple configuration

The easiest way to add a gallery to a resource is to set up an array of numbers.

```php
<?php return [1, 3, 5];
```

This array contains a list of resource template IDs.
If you use this simple setting, an additional tab with a gallery will be added to the resources with the template listed.

## More than one tab

Since 1.7.0, use the resource template ID as the key and a list of tab names as its value:

```php
<?php return [
    5 => ['slider', 'top-sales'],
    1,
    3,
];
```

Template 5 receives two named galleries. Templates 1 and 3 receive a standard gallery tab.
Keep named assignments before simple IDs: PHP automatically allocates integer keys to unkeyed entries, and a later explicit key could overwrite an earlier entry. The settings interface uses this order when saving. Simple configurations such as `[1, 3, 5]` remain supported and are still written as plain ID lists.

## Deprecated nested assignments

The following format is deprecated since 1.7.0, but remains readable for backward compatibility:

```php
<?php return [
    1,
    3,
    [5 => ['slider', 'top-sales']],
];
```

The manager shows a deprecation notice when this format is present. Changing gallery settings and saving converts assignments to the flat format. Merely opening the page or saving unrelated system settings does not rewrite the gallery configuration. Older package versions still support the simple ID list, but require the nested format for named tabs.

## Generated image cache

Use the `imageCacheDir` key to choose the project-relative public directory for generated image derivatives.

```php
<?php return [
    1,
    'imageCacheDir' => 'assets/derived/',
];
```

The default is `assets/cache/` for backward compatibility. After changing the directory, run `php artisan vendor:publish --tag=sGallery`. It creates `.gitignore` and `index.html` in the selected directory without changing existing generated images.

## Manager settings

Since 1.7.0, the system configuration page includes an sGallery tab for image quality,
sharpening, output format, optimization, cache management, watermark defaults and
resource template assignments. Use the system Save action: gallery settings are
written only when their values have changed. Saving requires manager settings
permissions and a valid request token.

Evolution loads `core/custom/config/seiger/settings/sGallery.php` automatically.
When it is absent, sGallery uses `config/sGallerySettings.php` from the package.
The custom file replaces the package defaults without merging template assignments.
The interface writes short PHP arrays with four-space indentation.

Thumbnail dimensions, the thumbnail fit method and the frontend optimization time
budget are package values, not editable settings. Configure each image transformation
through Builder methods such as `fit()` and `resize()`.

## Watermarks

Watermarks are opt-in: existing image calls remain unwatermarked unless they call
`watermark()`. All three forms are supported:

```php
$options = ['position' => 'bottom-right', 'width' => '20%', 'opacity' => 100, 'padding' => '3%'];

sGallery::file($product->cover)->fit('crop', 800, 800)->watermark($watermarkPath, $options);
sGallery::file($product->cover)->fit('crop', 800, 800)->watermark($options);
sGallery::file($product->cover)->fit('crop', 800, 800)->watermark();
```

Without an explicit path, Builder uses `watermark.file` from settings. An empty
default path leaves the image unwatermarked. Select the default file with the
manager image browser; the file must be a local image supported by the driver.
Options override configured defaults for the current call only.

`width` and `padding` accept integer pixels or percentages. Width is measured
against the processed image width after resize/crop; percentage horizontal and
vertical padding use the processed width and height respectively. Watermark aspect
ratio is preserved. Opacity ranges from 0 (transparent) to 100 (opaque).

Watermarks are applied to cached derivatives after image transformations. Originals
remain unchanged, and repeated Builder calls reuse the derivative. The cache key
includes source and watermark contents and processing parameters, so changing the
watermark or its options selects a new cached file.

The cache-clear button removes indexed sGallery derivatives and protects recorded
source files. Files created before derivative indexing and unrelated cache files
are left intact.

## Output formats

Builder supports `jpg`, `jpeg`, `png`, `gif`, `webp`, `avif` and `original`, subject
to encoder availability. The visual settings offer AVIF, WebP and the original
extension. `format('original')` preserves the source extension while still applying
transformations; it does not mean returning an unprocessed original.
