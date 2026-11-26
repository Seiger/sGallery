---
id: fit
title: Ajustement
slug: /manipulations/fit/
sidebar_position: 2
---

# Ajustement

`fit($method, $width, $height)` détermine comment une image occupe un cadre en pixels. Par défaut, la hauteur est égale à la largeur.

```php
$url = sGallery::file($item->src)->fit('max', 500, 300)->getFile();
```

| Mode | Résultat |
| --- | --- |
| `contain` | Insère l’image dans le cadre en conservant ses proportions. |
| `max` | Ajuste l’image sans l’agrandir. |
| `fill` | Ajuste sans agrandir et remplit le reste du cadre. |
| `stretch` | Impose les dimensions, quitte à modifier les proportions. |
| `crop` | Remplit le cadre et coupe ce qui dépasse. |
| `fill-max` | Peut agrandir l’image puis remplit le cadre. |
