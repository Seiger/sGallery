---
id: use-in-blade
title: Użycie w Blade
slug: /use-in-blade/
sidebar_position: 4
---

# Użycie w Blade

Wybieraj wpisy według typu. Do wyświetlania adresów i tekstu alternatywnego korzystaj z escapowanych wyrażeń Blade.

```blade
@foreach (sGallery::collections()->get() as $item)
    @if (sGallery::hasImage($item->type))
        <img src="{{ $item->src }}" alt="{{ $item->alt }}" loading="lazy">
    @endif
@endforeach
```

Dla produktu użyj `itemType('product')` i `documentId((int) $product->id)`. Metody `blockName()` i `lang()` wybierają blok i język.
