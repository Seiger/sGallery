---
id: use-in-blade
title: Użycie w Blade
slug: /use-in-blade/
sidebar_position: 4
---

# Użycie w Blade

## Miniatura z pliku

Przekaż ścieżkę pliku do `@thumb`. Dyrektywa wypisuje adres URL miniatury przyciętej do 240 × 120 pikseli i koduje znaki specjalne HTML.

```blade
<img src="@thumb($item->src)" alt="{{ $item->alt }}">
```

## Obrazy zasobu

Przejdź po kolekcji i sprawdź typ każdej pozycji. Dla adresu URL i tekstu alternatywnego używaj składni Blade z escapowaniem.

```blade
@foreach (sGallery::collections()->get() as $item)
    @if (sGallery::hasImage($item->type))
        <img src="{{ sGallery::file($item->src)->fit('max', 800, 600)->format('webp')->getFile() }}"
             alt="{{ $item->alt }}" loading="lazy">
    @endif
@endforeach
```

## Galeria produktu lub nazwany blok

```php
$items = sGallery::collections()
    ->itemType('product')
    ->documentId((int) $product->id)
    ->blockName('slider')
    ->lang('uk')
    ->get();
```

Dla bieżącego zasobu możesz pominąć typ i identyfikator. Aby pobrać pojedynczą pozycję, użyj `sGallery::first($itemType, $parentId, $lang, $block)`. Metoda zwraca model również wtedy, gdy nie znajdzie pozycji. Przed wyświetleniem sprawdź, czy rekord istnieje.

Rozpoznawaj filmy za pomocą `sGallery::hasYoutube($item->type)`. Dla załączonych plików wyświetl adres `$item->src` z escapowaniem. Zobacz też [poradnik programisty](./developer-guide.md).
