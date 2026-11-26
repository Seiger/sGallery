---
id: getting-started
title: Getting started
slug: /getting-started/
sidebar_position: 2
---

# Getting started

## Install in Evolution CMS

sGallery 2.x requires PHP 8.4 or later in the 8.x series, Evolution CMS 3.6 or later in the 3.x series and Composer. Install GD or Imagick for image derivatives. sTask is optional.

From the Evolution project root, run:

```bash
cd core
php artisan package:installrequire seiger/sgallery "^2.0"
php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"
php artisan migrate
```

## Add a resource gallery

Open **System configuration → sGallery**, assign a resource template and save. Alternatively, add the template ID to `core/custom/config/seiger/settings/sGallery.php`:

```php
<?php return [5];
```

Open a resource that uses the template and add a photo. Follow the [editor guide](./user-guide.md) to order media and edit alternative text, or use the [Blade examples](./use-in-blade.md) to display the gallery.
