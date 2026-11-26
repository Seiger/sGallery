---
id: reference
title: Dokumentacja referencyjna
sidebar_position: 8
---

# Dokumentacja referencyjna

## Formaty

Obsługa zależy od kodeków dostępnych w zainstalowanym rozszerzeniu PHP.

| Format | GD | Imagick |
| --- | --- | --- |
| JPEG, PNG, GIF, WebP, AVIF | Odczyt i zapis | Odczyt i zapis |
| BMP | Odczyt | Odczyt |
| Jednostronicowy TIFF | | Odczyt |
| Statyczne HEIC / HEIF | | Odczyt z Fileinfo i dekoderem HEIF |
| Statyczny JPEG XL | | Odczyt z dekoderem JPEG XL |

Do przekształcania animowanych plików GIF i WebP potrzebny jest Imagick. Gdy brakuje kodeka lub operacja jest niedostępna, Builder może zwrócić adres oryginału. `format('original')` zachowuje rozszerzenie pliku, jeśli koder obsługuje dany format.

## Metody Buildera

| Metoda | Zastosowanie |
| --- | --- |
| `collections()`, `documentId()`, `itemType()`, `blockName()`, `lang()`, `get()` | Wybór pozycji według zasobu, bloku i języka |
| `file()`, `getFile()` | Wskazanie źródła i pobranie adresu wyniku |
| `resize()`, `fit()`, `crop()` | Ustawienie wymiarów, dopasowanie i kadrowanie |
| `manualCrop()`, `focalCrop()` | Wybór obszaru według współrzędnych źródła |
| `format()`, `quality()`, `optimize()` | Ustawienie formatu, jakości i optymalizacji |
| `watermark()`, `sharpen()` | Znak wodny i ostrość |

Szczegółowe przykłady znajdziesz w [poradniku przetwarzania obrazów](./manipulations/index.md).
