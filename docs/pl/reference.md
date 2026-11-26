---
id: reference
title: Informacje referencyjne
sidebar_position: 8
---

# Informacje referencyjne

Obsługa kodeków zależy od instalacji. GD i Imagick odczytują i zapisują JPEG, PNG, GIF, WebP i AVIF. BMP jest formatem wejściowym. Jednostronicowy TIFF, HEIC/HEIF i JPEG XL wymagają odpowiedniego dekodera Imagick. Przetwarzanie animacji GIF i WebP wymaga Imagick.

| Metoda | Zastosowanie |
| --- | --- |
| `resize()`, `fit()`, `crop()` | Rozmiar i kadrowanie |
| `manualCrop()`, `focalCrop()` | Kadrowanie według współrzędnych |
| `format()`, `quality()`, `optimize()` | Format i kompresja |
| `watermark()`, `sharpen()` | Znak wodny i wyostrzanie |
| `getFile()` | Adres wyniku |

Jeśli operacja nie jest obsługiwana, Builder może zwrócić adres oryginału.
