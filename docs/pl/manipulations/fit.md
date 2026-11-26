---
id: fit
title: Dopasowanie
slug: /manipulations/fit/
sidebar_position: 2
---

# Dopasowanie

`fit($method, $width, $height)` określa sposób dopasowania obrazu do ramki mierzonej w pikselach. Jeśli pominiesz wysokość, jest ona równa szerokości.

```php
$url = sGallery::file($item->src)->fit('max', 500, 300)->getFile();
```

| Tryb | Wynik |
| --- | --- |
| `contain` | Mieści obraz w ramce i zachowuje proporcje. |
| `max` | Mieści obraz w ramce bez powiększania. |
| `fill` | Dopasowuje bez powiększania i wypełnia wolne brzegi. |
| `stretch` | Nadaje dokładne wymiary. Proporcje mogą się zmienić. |
| `crop` | Wypełnia ramkę i przycina wystające krawędzie. |
| `fill-max` | Dopasowuje obraz do ramki, w razie potrzeby powiększa i wypełnia brzegi. |

Aby ustawić kadr według położenia lub współrzędnych, użyj [kadrowania](./crop.md).
