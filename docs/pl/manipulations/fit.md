---
id: fit
title: Dopasowanie
slug: /manipulations/fit/
sidebar_position: 2
---

# Dopasowanie

`fit($method, $width, $height)` określa, jak obraz ma zmieścić się w ramce podanej w pikselach. Domyślna wysokość jest równa szerokości.

```php
$url = sGallery::file($item->src)->fit('max', 500, 300)->getFile();
```

| Tryb | Wynik |
| --- | --- |
| `contain` | Mieści obraz w ramce, zachowując proporcje. |
| `max` | Dopasowuje obraz bez powiększania. |
| `fill` | Dopasowuje bez powiększania i wypełnia wolne miejsce. |
| `stretch` | Ustawia dokładne rozmiary, w razie potrzeby zmieniając proporcje. |
| `crop` | Wypełnia ramkę i odcina wystające fragmenty. |
| `fill-max` | W razie potrzeby powiększa obraz, a następnie wypełnia tło. |
