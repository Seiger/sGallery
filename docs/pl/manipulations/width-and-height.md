---
id: width-and-height
title: Szerokość i wysokość
slug: /manipulations/width-and-height/
sidebar_position: 5
---

# Szerokość i wysokość

`resize()`, `fit()` i `crop()` przyjmują wymiary w pikselach. Pominięta wysokość jest równa szerokości. Tryb zachowujący proporcje może mimo to utworzyć prostokątny wynik.

```php
$url = sGallery::file($item->src)->fit('max', 500, 300)->getFile();
```
