---
id: fit
title: Einpassen
slug: /manipulations/fit/
sidebar_position: 2
---

# Einpassen

Mit `fit($method, $width, $height)` wählen Sie, wie das Bild eine Fläche in Pixeln ausfüllt. Ohne Höhenangabe entspricht die Höhe der Breite.

```php
$url = sGallery::file($item->src)->fit('max', 500, 300)->getFile();
```

| Modus | Ergebnis |
| --- | --- |
| `contain` | Passt das Bild unter Beibehaltung des Seitenverhältnisses ein. |
| `max` | Passt das Bild ohne Vergrößerung ein. |
| `fill` | Passt ohne Vergrößerung ein und füllt den übrigen Rand auf. |
| `stretch` | Setzt die genauen Abmessungen, auch wenn sich das Seitenverhältnis ändert. |
| `crop` | Füllt die Fläche aus und schneidet den Überstand ab. |
| `fill-max` | Vergrößert bei Bedarf und füllt den übrigen Rand auf. |
