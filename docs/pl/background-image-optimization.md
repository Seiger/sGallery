---
id: background-image-optimization
title: Optymalizacja obrazów w tle
slug: /background-image-optimization/
sidebar_position: 7
---

# Optymalizacja obrazów w tle

Małe obrazy sGallery przetwarza podczas żądania WWW. Budżet pięciu sekund jest sprawdzany między etapami. Bieżącego kodowania nie można w ten sposób przerwać. Wolne przekształcenie może zostać odłożone do sTask, a strona tymczasowo korzysta z oryginalnego adresu.

## Użyj sTask

Runner musi działać i aktualizować sygnał aktywności. Uruchom go z katalogu `core`:

```bash
php artisan stask:worker
```

Przy pierwszym odłożonym zadaniu sGallery rejestruje `sgallery.image-optimization`. Jeśli istniejący worker jest wyłączony, włącz go w sTask. Runner musi mieć zapis do katalogu pochodnych i współdzielonego `storage/sgallery/optimization`.

## Pamięć podręczna i ponawianie

Identyczne aktywne zadanie jest wykorzystywane ponownie. Po błędzie kolejną próbę można zakolejkować po pięciu minutach. Zadanie przypisane do starej wersji źródła nie publikuje wyniku. Po sprawdzeniu obraz trafia do pamięci podręcznej atomowo. Przetwarzanie animowanych GIF i WebP wymaga Imagick.
