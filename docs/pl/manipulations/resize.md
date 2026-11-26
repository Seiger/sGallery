---
id: resize
title: Zmiana rozmiaru
slug: /manipulations/resize/
sidebar_position: 1
---

# Zmiana rozmiaru

`resize($width, $height)` przyjmuje rozmiary w pikselach. Jeśli pominiesz wysokość, Builder użyje szerokości w obu miejscach. Zobacz [Dopasowanie](./fit.md), gdy trzeba kontrolować proporcje lub wypełniać tło.

```php
$url = sGallery::file($item->src)->resize(250, 200)->getFile();
$equalBounds = sGallery::file($item->src)->resize(250)->getFile();
```
