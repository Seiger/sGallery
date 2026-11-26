---
id: getting-started
title: Getting started
slug: /getting-started/
sidebar_position: 2
---

# Getting started

## Install in Evolution CMS

sGallery 1.9 requires PHP 8.4 or later in the 8.x series, Evolution CMS 3.5 or later in the 3.x series and Composer. Install GD or Imagick for image derivatives. Spatie Image and sTask are optional.

Fresh installations on PHP 8.6 were tested with [Evolution CMS 3.5.x at ea42b18c](https://github.com/evolution-cms/evolution/commit/ea42b18c8bbadbcb9b994c8f8cb61e824398bb17). If you use sTask to process images with Evolution CMS 3.5.9, update sTask to [2.3.2](https://github.com/Seiger/sTask/releases/tag/v2.3.2) for the repeated migration fix.

From the Evolution project root, run:

```bash
cd core
php artisan package:installrequire seiger/sgallery "^1.9"
php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"
php artisan migrate
```

## Add a resource gallery

Open **System configuration → sGallery**, assign a resource template and save. Alternatively, add the template ID to `core/custom/config/seiger/settings/sGallery.php`:

```php
<?php return [5];
```

Open a resource that uses the template and add a photo. Follow the [editor guide](./user-guide.md) to order media and edit alternative text, or use the [Blade examples](./use-in-blade.md) to display the gallery.
