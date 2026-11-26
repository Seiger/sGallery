---
id: fit
title: Anpassung
slug: /manipulations/fit/
sidebar_position: 2
---

# Anpassung

Mit `fit($method, $width, $height)` legen Sie fest, wie das Bild in einen in Pixeln gemessenen Rahmen eingepasst wird. Ohne Höhenangabe gilt die Breite auch für die Höhe.

```php
$url = sGallery::file($item->src)->fit('max', 500, 300)->getFile();
```

| Modus | Ergebnis |
| --- | --- |
| `contain` | Passt das Bild mit unveränderten Proportionen in den Rahmen ein. |
| `max` | Passt das Bild ohne Vergrößerung ein. |
| `fill` | Passt das Bild ohne Vergrößerung ein und füllt die freien Ränder. |
| `stretch` | Erzeugt die genauen Abmessungen. Die Proportionen können sich ändern. |
| `crop` | Füllt den Rahmen aus und schneidet Überstände ab. |
| `fill-max` | Passt das Bild ein, vergrößert es bei Bedarf und füllt die Ränder. |

Für einen Ausschnitt nach Position oder Koordinaten verwenden Sie den [Zuschnitt](./crop.md).
