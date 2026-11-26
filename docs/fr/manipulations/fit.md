---
id: fit
title: Ajustement
slug: /manipulations/fit/
sidebar_position: 2
---

# Ajustement

`fit($method, $width, $height)` définit la façon dont l’image s’inscrit dans un cadre mesuré en pixels. Si la hauteur est omise, elle prend la valeur de la largeur.

```php
$url = sGallery::file($item->src)->fit('max', 500, 300)->getFile();
```

| Mode | Résultat |
| --- | --- |
| `contain` | Inscrit l’image dans le cadre en conservant ses proportions. |
| `max` | Inscrit l’image dans le cadre sans l’agrandir. |
| `fill` | Inscrit l’image sans l’agrandir et comble les marges. |
| `stretch` | Impose les dimensions exactes et peut modifier les proportions. |
| `crop` | Remplit le cadre et coupe ce qui dépasse. |
| `fill-max` | Inscrit l’image dans le cadre, l’agrandit si nécessaire et comble les marges. |

Pour définir le cadre selon une position ou des coordonnées, utilisez le [recadrage](./crop.md).
