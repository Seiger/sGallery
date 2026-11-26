---
id: crop
title: Recadrage
slug: /manipulations/crop/
sidebar_position: 3
---

# Recadrage

- Utilisez `crop($position, $width, $height)` pour choisir un emplacement parmi `topLeft`, `top`, `topRight`, `left`, `center`, `right`, `bottomLeft`, `bottom`, `bottomRight`.
- `focalCrop($x, $y, $width, $height)` centre le cadre sur un point si possible puis le déplace vers l’intérieur près des bords.
- `manualCrop($x, $y, $width, $height)` fixe le coin supérieur gauche. Le traitement natif limite le débordement et retourne l’original si ce coin tombe hors de l’image.

Les coordonnées partent du coin supérieur gauche de la source. La hauteur omise prend la valeur de la largeur.

```php
$url = sGallery::file($item->src)->focalCrop(50, 50, 250, 200)->getFile();
```
