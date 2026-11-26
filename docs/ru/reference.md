---
id: reference
title: Справочник
sidebar_position: 8
---

# Справочник

JPEG, PNG, GIF, WebP и AVIF доступны для чтения и записи в GD и Imagick при наличии подходящих кодеков. BMP доступен для чтения. Одностраничный TIFF, HEIC/HEIF и JPEG XL требуют соответствующего декодера Imagick. Преобразование анимированных GIF и WebP использует Imagick.

| Метод | Назначение |
| --- | --- |
| `resize()`, `fit()`, `crop()` | Размер и кадрирование |
| `manualCrop()`, `focalCrop()` | Кадрирование по координатам |
| `format()`, `quality()`, `optimize()` | Формат и сжатие |
| `watermark()`, `sharpen()` | Водяной знак и резкость |
| `getFile()` | URL результата |

Если операция недоступна, Builder может вернуть URL оригинала.
