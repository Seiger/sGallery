# sGallery for Evolution CMS
![sGallery](https://user-images.githubusercontent.com/12029039/169609394-08ea36d6-2393-4261-aff2-348f73a6103c.png)
[![Latest Stable Version](https://img.shields.io/packagist/v/seiger/sgallery?label=version)](https://packagist.org/packages/seiger/sgallery)
[![Evolution CMS version requirement](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2FSeiger%2FsGallery%2F2.x%2Fcomposer.json&query=%24.require%5B%27evolution-cms%2Fevolution%27%5D&label=CMS&prefix=Evolution%20&color=brightgreen)](https://github.com/evolution-cms/evolution)
[![PHP: 8.4, 8.5 and 8.6](https://img.shields.io/badge/PHP-8.4_%7C_8.5_%7C_8.6-777BB4?logo=php&logoColor=white)](#compatibility)
[![Issues](https://img.shields.io/github/issues/Seiger/sgallery)](https://github.com/Seiger/sgallery/issues)
[![Stars](https://img.shields.io/packagist/stars/Seiger/sgallery)](https://packagist.org/packages/seiger/sgallery)
[![Total Downloads](https://img.shields.io/packagist/dt/seiger/sgallery)](https://packagist.org/packages/seiger/sgallery)
[![License](https://img.shields.io/packagist/l/seiger/sgallery)](https://packagist.org/packages/seiger/sgallery)
![Made in Ukraine](https://img.shields.io/badge/made_in-Ukraine-ffd700.svg?labelColor=0057b7)

Attach images, downloads and YouTube videos to Evolution CMS resources, manage
them in the manager and generate image derivatives through a fluent PHP Builder.
GD and Imagick handle image processing. sTask can run slow transformations in
the background.

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

The Builder uses `NativeImage` for supported still images. It checks the source and output formats for each driver,
prefers Imagick when both can process the image, and retries with the other
driver when a still image fails. Animated GIF and WebP use Imagick. The
original stays available if the requested output cannot preserve animation.
Native codec availability depends on the installed GD or Imagick build.

Derivative URLs include a revision of the full source path, size, modification
time and SHA-256 contents. Replacing a local file also refreshes its cache when
size and mtime are preserved. Earlier derivatives stay available until cache
clearing. Fingerprints stream the source bytes without decoding them; cache
lookups still require reading the source. The Builder rechecks the revision
before and after publication, and sTask rejects stale hashed jobs.
Earlier size/mtime-only jobs validate their available metadata and then pin
the current contents before processing.
Gallery optimization descriptors are normalized to their persisted JSON types
before sTask compares active jobs. Integral floats such as sharpening `0.0`
match stored `0`; fractional strengths, booleans and strings remain distinct.
Existing active tasks retain their attempt counts and progress; completed or
failed work follows the existing retry and cooldown rules.

| Format | Native GD | Native Imagick |
| --- | --- | --- |
| JPEG | Read and write | Read and write |
| PNG | Read and write | Read and write |
| GIF | Read and write | Read and write |
| WebP | Read and write | Read and write |
| AVIF | Read and write | Read and write |
| BMP | Read | Read |
| HEIC / HEIF (still image) | — | Read with Fileinfo and an HEIF decoder |
| JPEG XL (still image) | — | Read with a JPEG XL decoder |
| TIFF (single page) | — | Read |

### Compatibility

- Evolution CMS **3.5 or later in the 3.x series**
- PHP **8.4, 8.5 and 8.6** (minimum 8.4)
- Composer **2.2+**
- One of: **MySQL 8.0+** / **MariaDB 10.5+** / **PostgreSQL 10+** / **SQLite 3.25+**

### Image processing

With a compatible GD or Imagick codec, the Builder can
use `fit()`, `crop()`, `manualCrop()` and `focalCrop()` on local still JPEG, PNG,
GIF, WebP, AVIF and BMP images, plus single-page TIFF through Imagick, and produce
JPEG, PNG, WebP, AVIF or GIF. For example,
`sGallery::file('assets/photo.jpg')->fit('crop', 320, 240)->format('webp')->getFile()`.
The native fit supports `crop`, `contain`, `max`, `fill`, `stretch` and `fill-max`.
The native Builder also reads still HEIC/HEIF and JPEG XL inputs through Imagick
and produces JPEG, PNG, WebP, AVIF or GIF derivatives, subject to output codec
availability. Missing decoders, unsupported color metadata and multi-frame inputs
return the original URL. `format('original')` returns these input files directly.
JPEG XL codestreams and containers are identified by content. Gallery uploads
accept `.jxl` when the CMS upload settings allow it and the server MIME agrees.
Native `resize()` works with automatic or explicit output formats without a
`quality()` call. It preserves the existing width-then-height behavior: on a
32 × 16 source, `resize(16, 8)` produces 16 × 8, while `resize(16, 16)` produces
32 × 16. Use `fit('max', 16, 16)` for a result inside a bounding box.
`format('jpg')`, `format('jpeg')`, `format('png')` and `format('webp')` also work
without resize or another operation, using the configured quality and optimization.
An unavailable codec returns the original URL. For still AVIF and
GIF, call `format('avif')` or `format('gif')` with or without a transform on a
supported local still image. If AVIF encoding fails or is unavailable, the Builder
tries WebP. GIF uses a palette with binary transparency.

With Imagick, native GIF and WebP animations keep their frames, delays and loop
count. A geometry request uses the source format by default. You can also request
GIF or WebP explicitly. The Builder returns the original URL if the encoder is
unavailable, the request exceeds the processing budget, or the selected output
cannot preserve the timing or color profile. GIF delays use 10 ms units.

Explicit `quality()` is clamped to
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

For native sources with an embedded ICC profile, Imagick with
LittleCMS converts the source to sRGB before resizing or compositing. The
derivative carries the bundled sRGB profile in JPEG, PNG, WebP and AVIF. GIF
uses the converted sRGB pixels without requiring a profile. A source
that requires a color conversion the server cannot perform keeps its original
URL. This includes ICC-tagged images on GD-only installations, CMYK JPEGs
without an ICC profile, and PNGs with unsupported color metadata. TIFF is probed
for page count and orientation before processing. Unsupported AVIF color/rotation
metadata, WebP EXIF without a supported profile, and BMP color metadata the driver
cannot interpret return the original URL. AVIF sequences are not flattened to stills.

The bundled [sRGB profile](https://github.com/saucecontrol/Compact-ICC-Profiles/blob/bdd84663/profiles/sRGB-v2-micro.icc)
and the Display P3 test fixture come from Compact ICC Profiles and use CC0-1.0.

`watermark()` supports local still watermark files on native derivatives. GD or
Imagick applies the mark after geometry, using the configured size, position,
padding and opacity. With Imagick the same mark is applied to each animation frame.

Native `sharpen()` applies a strength from 0 to 100 after geometry and before
`watermark()`. It preserves transparency and uses Imagick for each animation frame.

Native `optimize()` uses PNG compression level 9 and progressive JPEG encoding.
Imagick also uses optimized JPEG coding and WebP method 6, and removes descriptive
metadata while keeping ICC profiles. Other formats use the selected codec's
compression. Explicit quality, transparency and animation timing are preserved.
The method works without a geometry request and requires no external optimizer
executables. Optimized derivatives use a separate cache key.
`optimize(false)` uses the regular encoding
settings and disables adaptive WebP quality selection.

Background optimization through sTask uses the same pipeline, including geometry,
watermarks, sharpening and output settings. If a transformation or codec is unavailable,
the Builder returns the original URL. Missing files use the normal placeholder.

When upgrading from 1.x, matching native derivatives are reused and queued sTask
jobs keep their existing descriptor format. Derivatives previously generated with
Spatie stay on disk. New requests use the native cache key.

## Install by artisan package installer

Go to You /core/ folder:

```console
cd core
```

Run php artisan command

```console
php artisan package:installrequire seiger/sgallery "^2.0"
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

[Public documentation](https://seiger.github.io/sGallery/) · [Documentation sources](docs/README.md)

## Background image optimization

Slow transformations can be processed by the optional sTask ImageOptimizationWorker, while the frontend temporarily serves the original. See [background image optimization](docs/en/background-image-optimization.md) for requirements, timing limits and recovery behavior.

Documentation: [English](docs/en/background-image-optimization.md) · [Українська](docs/uk/background-image-optimization.md)
