---
id: width-and-height
title: Ширина и высота
slug: /manipulations/width-and-height/
sidebar_position: 5
---

# Ширина и высота

`resize()`, `fit()` и `crop()` принимают размеры в пикселях. Если высоту не указать, она равна ширине. Подгонка с сохранением пропорций может вернуть прямоугольное изображение.

```php
$url = sGallery::file($item->src)->fit('max', 500, 300)->getFile();
```
