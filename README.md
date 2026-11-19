# sGallery for Evolution CMS
![sGallery](https://user-images.githubusercontent.com/12029039/169609394-08ea36d6-2393-4261-aff2-348f73a6103c.png)
[![Latest Stable Version](https://img.shields.io/packagist/v/seiger/sgallery?label=version)](https://packagist.org/packages/seiger/sgallery)
[![Evolution CMS version requirement](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FSeiger%2FsGallery%2Fmain%2Fcomposer.json&query=%24.require%5B%27evolution-cms%2Fevolution%27%5D&label=CMS&prefix=Evolution%20&color=brightgreen)](https://github.com/evolution-cms/evolution)
[![PHP: 8.4, 8.5 and 8.6](https://img.shields.io/badge/PHP-8.4_%7C_8.5_%7C_8.6-777BB4?logo=php&logoColor=white)](#compatibility)
[![Issues](https://img.shields.io/github/issues/Seiger/sgallery)](https://github.com/Seiger/sgallery/issues)
[![Stars](https://img.shields.io/packagist/stars/Seiger/sgallery)](https://packagist.org/packages/seiger/sgallery)
[![Total Downloads](https://img.shields.io/packagist/dt/seiger/sgallery)](https://packagist.org/packages/seiger/sgallery)
[![License](https://img.shields.io/packagist/l/seiger/sgallery)](https://packagist.org/packages/seiger/sgallery)
![Made in Ukraine](https://img.shields.io/badge/made_in-Ukraine-ffd700.svg?labelColor=0057b7)

**sGallery** emerges as a versatile and indispensable plugin tailor-made for Evolution CMS,
revolutionizing the way you manage media assets within your website. Specifically
designed for the Evolution CMS admin panel, this dynamic plugin introduces a host
of powerful features to elevate your content presentation.

**sGallery** stands as a testament to Evolution CMS's commitment to robust and user-friendly
content management. Whether you are a content creator or an administrator, this plugin
empowers you to curate a visually stunning and engaging website with unparalleled ease.
Elevate your media management experience with **sGallery** today.

## Features

- [x] Image and Video Attachment.
- [x] Effortless Media Upload.
- [x] YouTube Integration.
- [x] Sortable Positions.
- [x] Text Fields for File Management.
- [x] Image Resize and AVIF or WEBP Conversion.
- [x] Default image optimization with optional sharpening controls.
- [x] Integration with Custom Modules.
- [x] **[sLang](https://github.com/Seiger/sLang)** Integration.
- [x] More than one tab.
- [x] Full Image slider in Admin panel.

## Supported formats

The gallery Builder's `format()` method offers JPEG, PNG, GIF, WebP and AVIF
as derivative formats.

The Builder uses `NativeImage` for supported still images and keeps Spatie for
transformations. It checks the source and output formats for each driver,
prefers Imagick when both can process the image, and retries with the other
driver when a still image fails. Animated GIF and WebP use Imagick. The
original stays available if the requested output cannot preserve animation.
Native codec availability depends on the installed GD or Imagick build.

| Format | Native GD | Native Imagick |
| --- | --- | --- |
| JPEG | Read and write | Read and write |
| PNG | Read and write | Read and write |
| GIF | Read and write | Read and write |
| WebP | Read and write | Read and write |
| AVIF | Read and write | Read and write |
| BMP | Read | Read |
| HEIC / HEIF (still image) | — | Read with Fileinfo and an HEIF decoder |
| TIFF (single page) | — | Read |

### Compatibility

- Evolution CMS **3.5 or later in the 3.x series**
- PHP **8.4, 8.5 and 8.6** (minimum 8.4)
- Composer **2.2+**
- One of: **MySQL 8.0+** / **MariaDB 10.5+** / **PostgreSQL 10+** / **SQLite 3.25+**

### Optional image processing

`spatie/image` is optional. With a compatible GD or Imagick codec, the Builder can
use `fit()`, `crop()`, `manualCrop()` and `focalCrop()` on local still JPEG and PNG
images and produce JPEG, PNG or WebP. For example,
`sGallery::file('assets/photo.jpg')->fit('crop', 320, 240)->format('webp')->getFile()`.
The native fit supports `crop`, `contain`, `max`, `fill`, `stretch` and `fill-max`.
`resize()` also supports explicit or configured WebP output. For still AVIF and
GIF, call `format('avif')` or `format('gif')` with or without a transform on a
local still JPEG or PNG. If AVIF encoding fails or is unavailable, the Builder
tries WebP. GIF uses a palette with binary transparency.

With Imagick, native GIF and WebP animations keep their frames, delays and loop
count. A geometry request uses the source format by default. You can also request
GIF or WebP explicitly. The Builder returns the original URL if the encoder is
unavailable, the request exceeds the processing budget, or the selected output
cannot preserve the timing or color profile. GIF delays use 10 ms units.

The native path uses a separate cache key. Explicit `quality()` is clamped to
1–100 and uses one encoder setting, including native WebP quality 100. Fixed WebP
requests have separate cache entries when they can differ from automatic quality.
With the existing adaptive selection enabled, WebP tries qualities from 99 down
to 80 and stops at the first file within the size target, or keeps the final
candidate. This also applies to native WebP animations. Quality-only requests
work on supported local sources without a geometry operation.

For JPEG files, native processing applies EXIF orientation before fitting,
resizing or cropping. GD can read the
orientation through PHP EXIF, and Imagick can read it without that extension.
When neither is available to read the tag, the stored pixel orientation is used.
PNG and WebP derivatives preserve PNG transparency, including palette and color-key
transparency. Automatic format selection keeps alpha in PNG or WebP. A JPEG
requested by the caller or configuration composites transparency onto white.

For native JPEG and PNG sources with an embedded ICC profile, Imagick with
LittleCMS converts the source to sRGB before resizing or compositing. The
derivative carries the bundled sRGB profile in JPEG, PNG, WebP and AVIF. GIF
uses the converted sRGB pixels without requiring a profile. A source
that requires a color conversion the server cannot perform keeps its original
URL. This includes ICC-tagged images on GD-only installations, CMYK JPEGs
without an ICC profile, and PNGs with unsupported color metadata.

The bundled [sRGB profile](https://github.com/saucecontrol/Compact-ICC-Profiles/blob/bdd84663/profiles/sRGB-v2-micro.icc)
and the Display P3 test fixture come from Compact ICC Profiles and use CC0-1.0.

`watermark()` supports local still watermark files on native derivatives. GD or
Imagick applies the mark after geometry, using the configured size, position,
padding and opacity. With Imagick the same mark is applied to each animation frame.

Native `sharpen()` applies a strength from 0 to 100 after geometry and before
`watermark()`. It preserves transparency and uses Imagick for each animation frame.

When Spatie is installed, the Builder continues to use it for transformations.
Background image optimization still uses Spatie.
If a transformation or codec is unavailable without
Spatie, the Builder returns the original URL. Missing files use the normal placeholder.

To enable image transformations, install version 3 from the site's `core/` directory:

```console
composer require spatie/image:^3
```

The gallery itself does not require EXIF. If you choose to install `spatie/image`
without EXIF, its Composer requirement needs an explicit opt-out:

```console
composer require spatie/image:^3 --ignore-platform-req=ext-exif
```

Include that flag in subsequent Composer install/update commands on that server.
With the GD driver in Spatie, automatic camera-photo rotation requires EXIF.

## Install by artisan package installer

Go to You /core/ folder:

```console
cd core
```

Run php artisan command

```console
php artisan package:installrequire seiger/sgallery "*"
```

Generate the config file in **../core/custom/config/seiger/settings** with
name **sgallery.php** the file should return a
comma-separated list of templates.

```console
php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"
```

Run make DB structure with command:

```console
php artisan migrate
```

## Configure

Templates for displaying gallery tabs are configured in the

```console
core/custom/config/seiger/settings/sGallery.php
```

file, where the array contains template IDs for connecting the gallery.

## Usage in blade

Sow all files with Image filter:
```php
@foreach(sGallery::collections()->mediaType('image')->get() as $item)
    <a class="swiper-slide" @if(trim($item->link))href="{{$item->link}}"@endif>
        <div class="container">
            <img loading="lazy" class="intro__img" src="{{$item->src}}" alt="{{$item->alt}}" width="1440" height="456">
            <div class="intro__inner">
                <div class="h1__title">{{$item->title}}</div>
                <p class="intro__text">{{$item->description}}</p>
                @if(trim($item->link_text))<div class="btn background__mod">{{$item->link_text}}</div>@endif
            </div>
        </div>
    </a>
@endforeach
```
or YouTube filter
```php
@foreach(sGallery::collections()->mediaType('youtube')->get() as $item)
    <div class="item">
        <div class="video">
            <iframe width="560" height="315" src="https://www.youtube.com/embed/{{$item->file}}" title="YouTube video player" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
        <p>{{$item->title}}</p>
    </div>
@endforeach
```
or
```php
@foreach(sGallery::collections()->documentId($product->id)->itemType('product')->get() as $item)
    <div class="swiper-slide">
        <a class="js-trigger-fancybox" href="{{$item->src}}" data-fancybox="product-gallery">
            <img loading="lazy" src="{{$item->src}}" width="440" height="440" />
        </a>
    </div>
@endforeach
```

## Integration into the products module

Just paste this code in your View backend
```php
{!!sGallery::initialiseView()->viewType('section')->itemType('product')->idType('i')!!}
```

[See full documentation here](https://seiger.github.io/sGallery/)

## Background image optimization

Slow transformations can be processed by the optional sTask ImageOptimizationWorker, while the frontend temporarily serves the original. See [background image optimization](docs/pages/background-image-optimization.md) for requirements, timing limits and recovery behavior.

Documentation: [English](docs/pages/background-image-optimization.md) · [Українська](docs/pages/uk/background-image-optimization.md) · [Русский](docs/pages/ru/background-image-optimization.md)
