---
id: developer-guide
title: Przewodnik dla deweloperów
sidebar_position: 6
---

# Przewodnik dla deweloperów

Metoda `collections()` pobiera wpisy galerii. W integracji wybierz typ i identyfikator obiektu nadrzędnego, blok oraz język. Dla własnego typu skonfiguruj [kontrolę dostępu](./configuration.md). Przykłady wyświetlania znajdują się w [przewodniku Blade](./use-in-blade.md).

```php
$url = sGallery::file($item->src)
    ->fit('max', 800, 600)
    ->format('webp')
    ->getFile();
```

`getFile()` zwraca adres wyniku. Przy aktywnym runnerze sTask wolniejsza konwersja może zostać wykonana w tle.
