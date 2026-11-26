---
id: resize
title: Redimensionnement
slug: /manipulations/resize/
sidebar_position: 1
---

# Redimensionnement

`resize($width, $height)` accepte des dimensions en pixels. Si la hauteur est omise, le Builder utilise la largeur pour les deux valeurs. Utilisez [Ajustement](./fit.md) pour contrôler les proportions et le remplissage.

```php
$url = sGallery::file($item->src)->resize(250, 200)->getFile();
$equalBounds = sGallery::file($item->src)->resize(250)->getFile();
```
