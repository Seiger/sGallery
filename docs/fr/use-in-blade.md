---
id: use-in-blade
title: Utilisation dans Blade
slug: /use-in-blade/
sidebar_position: 4
---

# Utilisation dans Blade

## Miniature à partir d’un fichier

Passez le chemin du fichier à `@thumb`. La directive affiche l’URL échappée d’une miniature recadrée à 240 × 120 pixels.

```blade
<img src="@thumb($item->src)" alt="{{ $item->alt }}">
```

## Images d’une ressource

Parcourez la collection et vérifiez le type de chaque entrée. Utilisez la syntaxe Blade avec échappement pour l’URL et le texte alternatif.

```blade
@foreach (sGallery::collections()->get() as $item)
    @if (sGallery::hasImage($item->type))
        <img src="{{ sGallery::file($item->src)->fit('max', 800, 600)->format('webp')->getFile() }}"
             alt="{{ $item->alt }}" loading="lazy">
    @endif
@endforeach
```

## Galerie de produit ou bloc nommé

```php
$items = sGallery::collections()
    ->itemType('product')
    ->documentId((int) $product->id)
    ->blockName('slider')
    ->lang('uk')
    ->get();
```

Pour la ressource courante, omettez le type et l’identifiant. Pour un seul élément, utilisez `sGallery::first($itemType, $parentId, $lang, $block)`. La méthode renvoie un modèle même si elle ne trouve aucune entrée. Vérifiez l’existence de l’enregistrement avant l’affichage.

Identifiez les vidéos avec `sGallery::hasYoutube($item->type)`. Pour les fichiers joints, affichez l’URL `$item->src` avec échappement. Consultez aussi le [guide de développement](./developer-guide.md).
