---
id: use-in-blade
title: Використання в Blade
slug: /use-in-blade/
sidebar_position: 4
---

# Використання в Blade

## Зображення ресурсу

Переберіть колекцію й перевірте тип кожного запису. Використовуйте екранований синтаксис Blade для URL та альтернативного тексту.

```blade
@foreach (sGallery::collections()->get() as $item)
    @if (sGallery::hasImage($item->type))
        <img src="{{ sGallery::file($item->src)->fit('max', 800, 600)->format('webp')->getFile() }}"
             alt="{{ $item->alt }}" loading="lazy">
    @endif
@endforeach
```

## Галерея товару або іменований блок

```php
$items = sGallery::collections()
    ->itemType('product')
    ->documentId((int) $product->id)
    ->blockName('slider')
    ->lang('uk')
    ->get();
```

Для поточного ресурсу можна опустити тип і ID. Для окремого запису використовуйте `sGallery::first($itemType, $parentId, $lang, $block)`. Метод повертає модель навіть за відсутності запису, тому перед виводом перевірте її наявність.

Перевіряйте відео за допомогою `sGallery::hasYoutube($item->type)`. Для вкладених файлів виводьте екранований URL `$item->src`. Дивіться також [посібник розробника](./developer-guide.md).
