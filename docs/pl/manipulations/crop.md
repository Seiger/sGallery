---
id: crop
title: Kadrowanie
slug: /manipulations/crop/
sidebar_position: 3
---

# Kadrowanie

- `crop($position, $width, $height)` wycina obszar w wybranym położeniu. Dostępne położenia to `topLeft`, `top`, `topRight`, `left`, `center`, `right`, `bottomLeft`, `bottom` i `bottomRight`.
- `focalCrop($x, $y, $width, $height)` próbuje umieścić wskazany punkt na środku kadru. Przy krawędzi przesuwa kadr do wnętrza obrazu.
- `manualCrop($x, $y, $width, $height)` wycina obszar od jego lewego górnego rogu. Natywny sterownik przycina część wystającą poza prawą lub dolną krawędź. Jeśli punkt początkowy leży poza obrazem, zwraca oryginał.

Współrzędne są liczone w pikselach od lewego górnego rogu obrazu. Jeśli pominiesz wysokość, jest ona równa szerokości.

```php
$url = sGallery::file($item->src)->focalCrop(50, 50, 250, 200)->getFile();
```
