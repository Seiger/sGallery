---
id: crop
title: Кадрирование
slug: /manipulations/crop/
sidebar_position: 3
---

# Кадрирование

- Метод `crop($position, $width, $height)` выбирает позицию кадра из `topLeft`, `top`, `topRight`, `left`, `center`, `right`, `bottomLeft`, `bottom`, `bottomRight`.
- `focalCrop($x, $y, $width, $height)` центрирует кадр на заданной точке и сдвигает его внутрь изображения возле края.
- `manualCrop($x, $y, $width, $height)` задаёт левый верхний угол. Нативный драйвер ограничивает выход за границы и возвращает оригинал, если начальная точка вне изображения.

Координаты отсчитывают в пикселях от левого верхнего угла. Пропущенная высота равна ширине.

```php
$url = sGallery::file($item->src)->focalCrop(50, 50, 250, 200)->getFile();
```
