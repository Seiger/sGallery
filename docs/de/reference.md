---
id: reference
title: Referenz
sidebar_position: 8
---

# Referenz

GD und Imagick können JPEG, PNG, GIF, WebP und AVIF lesen und schreiben, sofern die installierten Codecs es zulassen. BMP wird gelesen. Einseitiges TIFF, HEIC/HEIF und JPEG XL erfordern einen passenden Imagick-Decoder. Animationstransformationen für GIF und WebP benötigen Imagick.

| Methode | Zweck |
| --- | --- |
| `resize()`, `fit()`, `crop()` | Bildgröße oder Ausschnitt |
| `manualCrop()`, `focalCrop()` | Ausschnitt anhand von Koordinaten |
| `format()`, `quality()`, `optimize()` | Ausgabe und Komprimierung |
| `watermark()`, `sharpen()` | Wasserzeichen und Schärfe |
| `getFile()` | URL des Ergebnisses |

Bei nicht unterstützten Operationen kann die URL des Originals zurückgegeben werden.
