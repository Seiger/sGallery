---
id: crop
title: Kadrowanie
slug: /manipulations/crop/
sidebar_position: 3
---

# Kadrowanie

- Metoda `crop($position, $width, $height)` wybiera kadr z pozycji `topLeft`, `top`, `topRight`, `left`, `center`, `right`, `bottomLeft`, `bottom`, `bottomRight`.
- `focalCrop($x, $y, $width, $height)` środkowuje kadr względem punktu i przesuwa go do wnętrza przy krawędzi.
- `manualCrop($x, $y, $width, $height)` ustala lewy górny róg. Sterownik natywny obcina wystające fragmenty i zwraca oryginał, jeśli punkt początkowy leży poza obrazem.

Współrzędne liczy się od lewego górnego rogu w pikselach. Pominięta wysokość jest równa szerokości.

```php
$url = sGallery::file($item->src)->focalCrop(50, 50, 250, 200)->getFile();
```
