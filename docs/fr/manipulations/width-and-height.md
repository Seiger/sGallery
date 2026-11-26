---
id: width-and-height
title: Largeur et hauteur
slug: /manipulations/width-and-height/
sidebar_position: 5
---

# Largeur et hauteur

Les méthodes `resize()`, `fit()` et `crop()` prennent des valeurs en pixels. La hauteur omise prend la valeur de la largeur. Un ajustement qui conserve les proportions peut produire une image rectangulaire.

```php
$url = sGallery::file($item->src)->fit('max', 500, 300)->getFile();
```
