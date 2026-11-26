---
id: width-and-height
title: Largeur et hauteur
slug: /manipulations/width-and-height/
sidebar_position: 5
---

# Largeur et hauteur

`resize()`, `fit()` et `crop()` reçoivent les dimensions en pixels. Sans hauteur, la largeur et la hauteur du cadre sont égales. La taille réelle du dérivé dépend du [mode d’ajustement](./fit.md) et des proportions de la source.

```php
$url = sGallery::file($item->src)->fit('max', 500, 300)->getFile();
```
