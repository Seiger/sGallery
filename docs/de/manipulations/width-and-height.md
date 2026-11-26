---
id: width-and-height
title: Breite und Höhe
slug: /manipulations/width-and-height/
sidebar_position: 5
---

# Breite und Höhe

Die Methoden `resize()`, `fit()` und `crop()` erwarten Pixelwerte. Eine fehlende Höhe übernimmt die Breite. Ein Modus mit festem Seitenverhältnis kann dennoch ein nicht quadratisches Ergebnis liefern.

```php
$url = sGallery::file($item->src)->fit('max', 500, 300)->getFile();
```
