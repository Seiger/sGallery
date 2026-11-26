---
id: use-in-blade
title: Использование в Blade
slug: /use-in-blade/
sidebar_position: 4
---

# Использование в Blade

Проверяйте тип каждой записи и выводите URL и альтернативный текст через экранирование Blade.

```blade
@foreach (sGallery::collections()->get() as $item)
    @if (sGallery::hasImage($item->type))
        <img src="{{ $item->src }}" alt="{{ $item->alt }}" loading="lazy">
    @endif
@endforeach
```

Для галереи товара укажите `itemType('product')` и `documentId((int) $product->id)`. Методы `blockName()` и `lang()` выбирают блок и язык.
