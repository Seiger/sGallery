---
id: developer-guide
title: Poradnik programisty
sidebar_position: 6
---

# Poradnik programisty

## Odczyt galerii

`collections()` zwraca Builder. `itemType()`, `documentId()` i `blockName()` wybierają rekord nadrzędny i galerię. `lang()` określa język w razie potrzeby. [Poradnik Blade](./use-in-blade.md) zawiera przykłady dla zasobów i produktów.

Dla własnego typu użyj tej samej nazwy typu i bloku w panelu i na stronie. [Reguła dostępu](./configuration.md#manager-gallery-permissions) powinna sprawdzać zarówno uprawnienie do akcji, jak i dostęp do konkretnego rekordu nadrzędnego.

Szablon modułu może wyświetlić gotowy formularz panelu administracyjnego.

```blade
{!! sGallery::initialiseView()->viewType('section')->itemType('product')->idType('i')->blockName('photo') !!}
```

`idType()` wskazuje pole żądania z identyfikatorem rekordu nadrzędnego. Dostępne widoki to `tab`, `section` i `sectionDownloads`. Jeśli integracja z sGallery jest opcjonalna, przed wyświetleniem formularza sprawdź `evo()->bound('sGallery')`.

## Przetwarzanie obrazów

```php
$url = sGallery::file($item->src)
    ->fit('max', 800, 600)
    ->format('webp')
    ->getFile();
```

Bez Spatie Builder automatycznie wybiera GD lub Imagick na podstawie formatu źródła i wyniku. Szczegóły przekształceń znajdziesz w [poradniku przetwarzania obrazów](./manipulations/index.md). W przypadku dużych obrazów lub powolnych przekształceń zobacz [optymalizację w tle](./background-image-optimization.md).
