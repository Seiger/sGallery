---
id: developer-guide
title: Посібник розробника
sidebar_position: 6
---

# Посібник розробника

## Читання галереї

`collections()` повертає Builder. `itemType()`, `documentId()` і `blockName()` вибирають батьківський запис і галерею. За потреби `lang()` задає мову. Приклади для ресурсів і товарів наведено у [посібнику з Blade](./use-in-blade.md).

Для власного типу встановіть однакові тип і блок у вкладці менеджера та у фронтенді. [Політика доступу](./configuration.md#manager-gallery-permissions) має перевіряти дозвіл на дію і доступ до конкретного батьківського запису.

Шаблон модуля може вивести готову форму менеджера.

```blade
{!! sGallery::initialiseView()->viewType('section')->itemType('product')->idType('i')->blockName('photo') !!}
```

Метод `idType()` задає поле запиту з ID батьківського запису. Доступні шаблони `tab`, `section` і `sectionDownloads`. Якщо sGallery є необов’язковою інтеграцією, перевірте `evo()->bound('sGallery')` перед виводом.

## Обробка зображень

```php
$url = sGallery::file($item->src)
    ->fit('max', 800, 600)
    ->format('webp')
    ->getFile();
```

Builder автоматично підбирає GD або Imagick за форматом джерела та результату. Деталі перетворень є в [посібниках з обробки](./manipulations/index.md). Для повільних або великих зображень дивіться [фонову оптимізацію](./background-image-optimization.md).
