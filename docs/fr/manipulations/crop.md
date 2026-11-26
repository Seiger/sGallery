---
id: crop
title: Recadrage
slug: /manipulations/crop/
sidebar_position: 3
---

# Recadrage

- `crop($position, $width, $height)` extrait une zone à la position choisie. Les positions sont `topLeft`, `top`, `topRight`, `left`, `center`, `right`, `bottomLeft`, `bottom` et `bottomRight`.
- `focalCrop($x, $y, $width, $height)` essaie de centrer le point indiqué dans le cadre. Près du bord, la méthode décale le cadre vers l’intérieur de l’image.
- `manualCrop($x, $y, $width, $height)` extrait une zone à partir de son coin supérieur gauche. Le pilote natif coupe la partie qui dépasse à droite ou en bas. Il renvoie l’original si le point de départ se trouve hors de l’image.

Les coordonnées sont mesurées en pixels depuis le coin supérieur gauche de l’image. Si la hauteur est omise, elle prend la valeur de la largeur.

```php
$url = sGallery::file($item->src)->focalCrop(50, 50, 250, 200)->getFile();
```
