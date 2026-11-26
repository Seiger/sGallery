---
id: use-in-blade
title: Utilisation dans Blade
slug: /use-in-blade/
sidebar_position: 4
---

# Utilisation dans Blade

Filtrez les entrées par type. Les expressions Blade échappées permettent d’afficher les URL et les textes alternatifs.

```blade
@foreach (sGallery::collections()->get() as $item)
    @if (sGallery::hasImage($item->type))
        <img src="{{ $item->src }}" alt="{{ $item->alt }}" loading="lazy">
    @endif
@endforeach
```

Pour un produit, utilisez `itemType('product')` et `documentId((int) $product->id)`. Choisissez un bloc et une langue avec `blockName()` et `lang()`.
