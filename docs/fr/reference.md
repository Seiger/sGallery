---
id: reference
title: Référence
sidebar_position: 8
---

# Référence

GD et Imagick lisent et écrivent JPEG, PNG, GIF, WebP et AVIF lorsque les codecs sont disponibles. BMP est lu. Les TIFF monopages, HEIC/HEIF et JPEG XL demandent un décodeur Imagick compatible. Les GIF et WebP animés utilisent Imagick pour leurs transformations.

| Méthode | Usage |
| --- | --- |
| `resize()`, `fit()`, `crop()` | Taille et recadrage |
| `manualCrop()`, `focalCrop()` | Recadrage par coordonnées |
| `format()`, `quality()`, `optimize()` | Format et compression |
| `watermark()`, `sharpen()` | Filigrane et netteté |
| `getFile()` | URL du résultat |

Si un traitement est indisponible, le Builder peut retourner l’URL de l’original.
