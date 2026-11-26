---
id: reference
title: Reference
sidebar_position: 8
---

# Reference

## Native image formats

Actual support depends on the installed PHP extension and its codecs.

| Format | GD | Imagick |
| --- | --- | --- |
| JPEG, PNG, GIF, WebP, AVIF | Read and write | Read and write |
| BMP | Read | Read |
| Single-page TIFF | | Read |
| Still HEIC / HEIF | | Read with Fileinfo and an HEIF decoder |
| Still JPEG XL | | Read with a JPEG XL decoder |

Animation transforms for GIF and WebP use Imagick. A missing decoder or unsupported transform can return the original URL. Supported outputs are `jpg`, `jpeg`, `png`, `gif`, `webp`, `avif` and `original`. `format('original')` preserves the extension when the driver can write it.

## Builder methods

| Method | Purpose |
| --- | --- |
| `collections()`, `documentId()`, `itemType()`, `blockName()`, `lang()`, `get()` | Read gallery entries for a parent, block and locale |
| `file()`, `getFile()` | Open an image source and resolve the resulting URL |
| `resize()`, `fit()`, `crop()` | Set target dimensions or a crop |
| `manualCrop()`, `focalCrop()` | Select a region using source coordinates |
| `format()`, `quality()`, `optimize()` | Select the encoder, quality and optimization |
| `watermark()`, `sharpen()` | Add a watermark or adjust sharpness |

See [configuration](./configuration.md) for permissions, supported link schemes and cache locations.
