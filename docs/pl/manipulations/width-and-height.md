---
id: width-and-height
title: Szerokość i wysokość
slug: /manipulations/width-and-height/
sidebar_position: 5
---

# Szerokość i wysokość

`resize()`, `fit()` i `crop()` przyjmują wymiary w pikselach. Jeśli pominiesz wysokość, ramka ma równą szerokość i wysokość. Rzeczywisty rozmiar obrazu wynikowego zależy od [trybu dopasowania](./fit.md) i proporcji źródła.

```php
$url = sGallery::file($item->src)->fit('max', 500, 300)->getFile();
```
