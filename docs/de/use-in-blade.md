---
id: use-in-blade
title: Verwendung in Blade
slug: /use-in-blade/
sidebar_position: 4
---

# Verwendung in Blade

## Bilder einer Ressource

Durchlaufen Sie die Sammlung und prüfen Sie den Typ jedes Eintrags. Verwenden Sie die maskierte Blade-Ausgabe für URL und Alternativtext.

```blade
@foreach (sGallery::collections()->get() as $item)
    @if (sGallery::hasImage($item->type))
        <img src="{{ sGallery::file($item->src)->fit('max', 800, 600)->format('webp')->getFile() }}"
             alt="{{ $item->alt }}" loading="lazy">
    @endif
@endforeach
```

## Produktgalerie oder benannter Block

```php
$items = sGallery::collections()
    ->itemType('product')
    ->documentId((int) $product->id)
    ->blockName('slider')
    ->lang('uk')
    ->get();
```

Für die aktuelle Ressource können Sie Typ und ID weglassen. Rufen Sie einen einzelnen Eintrag mit `sGallery::first($itemType, $parentId, $lang, $block)` ab. Die Methode gibt auch ohne gefundenen Datensatz ein Modell zurück. Prüfen Sie vor der Ausgabe, ob der Datensatz vorhanden ist.

Prüfen Sie Videos mit `sGallery::hasYoutube($item->type)`. Geben Sie für angehängte Dateien die maskierte URL `$item->src` aus. Siehe auch das [Entwicklerhandbuch](./developer-guide.md).
