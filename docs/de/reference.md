---
id: reference
title: Referenz
sidebar_position: 8
---

# Referenz

## Formate

Die Unterstützung hängt von den Codecs der installierten PHP-Erweiterung ab.

| Format | GD | Imagick |
| --- | --- | --- |
| JPEG, PNG, GIF, WebP, AVIF | Lesen und Schreiben | Lesen und Schreiben |
| BMP | Lesen | Lesen |
| Einseitiges TIFF | | Lesen |
| Statisches HEIC / HEIF | | Lesen mit Fileinfo und HEIF-Decoder |
| Statisches JPEG XL | | Lesen mit JPEG-XL-Decoder |

Für die Verarbeitung animierter GIF- und WebP-Dateien ist Imagick erforderlich. Wenn ein Codec oder eine Operation fehlt, kann Builder die Original-URL zurückgeben. Mit `format('original')` bleibt die Dateiendung erhalten, sofern ein Encoder für das Format verfügbar ist.

## Builder-Methoden

| Methode | Zweck |
| --- | --- |
| `collections()`, `documentId()`, `itemType()`, `blockName()`, `lang()`, `get()` | Einträge nach Ressource, Block und Sprache auswählen |
| `file()`, `getFile()` | Bildquelle angeben und Ergebnis-URL abrufen |
| `resize()`, `fit()`, `crop()` | Abmessungen, Anpassung und Zuschnitt |
| `manualCrop()`, `focalCrop()` | Bereich anhand von Quellkoordinaten auswählen |
| `format()`, `quality()`, `optimize()` | Format, Qualität und Optimierung |
| `watermark()`, `sharpen()` | Wasserzeichen und Schärfe |

Weitere Beispiele stehen im [Handbuch zur Bildverarbeitung](./manipulations/index.md).
