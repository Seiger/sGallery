---
id: configuration
title: Konfiguracja
slug: /configuration/
sidebar_position: 3
---

# Konfiguracja

Ustawienia projektu znajdują się w `core/custom/config/seiger/settings/sGallery.php`. Przykład przypisuje dwa nazwane bloki do szablonu 5 i standardową galerię do szablonów 1 i 3.

```php
<?php return [
    5 => ['slider', 'top-sales'],
    1,
    3,
];
```

Odczyt wymaga dostępu do zasobu i uprawnienia `view_document` lub `edit_document`. Zmiana wymaga `edit_document` i `save_document`, a usunięcie dodatkowo `delete_document`. Dla własnych typów nadrzędnych zarejestruj zasady w `itemPolicies`.

Domyślna ścieżka wyników to `assets/cache/`. Można ją zmienić kluczem `imageCacheDir`. Czyszczenie pamięci podręcznej z panelu usuwa zindeksowane pochodne i pozostawia oryginały.
