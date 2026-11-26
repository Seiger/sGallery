---
id: use-in-blade
title: Verwendung in Blade
slug: /use-in-blade/
sidebar_position: 4
---

# Verwendung in Blade

Durchlaufen Sie die Einträge der aktuellen Ressource. Prüfen Sie den Medientyp und geben Sie URL und Alternativtext mit der maskierten Blade-Ausgabe aus.

```blade
@foreach (sGallery::collections()->get() as $item)
    @if (sGallery::hasImage($item->type))
        <img src="{{ $item->src }}" alt="{{ $item->alt }}" loading="lazy">
    @endif
@endforeach
```

Für ein Produkt wählen Sie `itemType('product')` und `documentId((int) $product->id)`. Mit `blockName()` und `lang()` legen Sie Block und Sprache fest.
