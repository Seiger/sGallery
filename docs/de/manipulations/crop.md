---
id: crop
title: Zuschnitt
slug: /manipulations/crop/
sidebar_position: 3
---

# Zuschnitt

- `crop($position, $width, $height)` schneidet einen Bereich an der gewählten Position aus. Mögliche Positionen sind `topLeft`, `top`, `topRight`, `left`, `center`, `right`, `bottomLeft`, `bottom` und `bottomRight`.
- `focalCrop($x, $y, $width, $height)` versucht, den angegebenen Punkt im Ausschnitt zu zentrieren. Am Rand verschiebt es den Rahmen ins Bild.
- `manualCrop($x, $y, $width, $height)` schneidet ab der oberen linken Ecke des Bereichs aus. Der native Treiber schneidet am rechten oder unteren Bildrand ab. Liegt der Startpunkt außerhalb des Bildes, gibt er das Original zurück.

Die Koordinaten werden in Pixeln ab der oberen linken Bildecke gemessen. Ohne Höhenangabe entspricht die Höhe der Breite.

```php
$url = sGallery::file($item->src)->focalCrop(50, 50, 250, 200)->getFile();
```
