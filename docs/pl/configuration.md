---
id: configuration
title: Konfiguracja
slug: /configuration/
sidebar_position: 3
---

# Konfiguracja

## Szablony i bloki

Panel zapisuje ustawienia w `core/custom/config/seiger/settings/sGallery.php`. Jeśli plik nie istnieje, obowiązują ustawienia domyślne z `config/sGallerySettings.php` w pakiecie. Ustawienia projektu zastępują domyślne przypisania do szablonów.

```php
<?php return [
    5 => ['slider', 'top-sales'],
    1,
    3,
];
```

Szablon 5 otrzymuje dwie nazwane karty, a szablony 1 i 3 galerię domyślną. Umieść nazwane przypisania przed samymi identyfikatorami, aby uniknąć powtarzających się kluczy liczbowych w tablicy. Starszy zapis zagnieżdżony `[1, 3, [5 => ['slider']]]` nadal działa. Po zmianie i zapisaniu ustawień sGallery panel zapisuje płaską tablicę.

## Uprawnienia w panelu {#manager-gallery-permissions}

Operacje na galerii wymagają sesji w panelu i dostępu do zasobu. Odczyt wymaga `view_document` lub `edit_document`. Dodawanie, sortowanie i tłumaczenia wymagają `edit_document` oraz `save_document`. Usuwanie wymaga dodatkowo `delete_document`. Domyślny blok nosi nazwę `1`.

Dla własnego typu, na przykład `product`, zarejestruj sprawdzenie dostępu w `itemPolicies`.

```php
'itemPolicies' => [
    'product' => [\App\Gallery\ProductPolicy::class, 'allows'],
],
```

Reguła przyjmuje `($action, $parentId, $block, $evolution)` i zwraca `true`, gdy dana akcja jest dozwolona. Dostępne akcje to `read`, `upload`, `uploadDownload`, `register`, `addYoutube`, `reorder`, `translate` i `delete`. Bez reguły typ niestandardowy jest dostępny dla administratorów.

Dodanie pliku z biblioteki wymaga `file_manager` albo odpowiedniego uprawnienia `assets_images` lub `assets_files`. Plik musi znajdować się w `rb_base_dir` i, jeśli ustawiono tę opcję, również w `image_base_upload_dir`. Odłączenie udostępnionego pliku pozostawia oryginał w bibliotece.

## Formaty, znaki wodne i pamięć podręczna {#watermarks}

W **Konfiguracja systemu → sGallery** wybierz format i jakość obrazów, ostrość, optymalizację, katalog pamięci podręcznej oraz domyślne ustawienia znaku wodnego. Podczas zapisu panel zapisuje tylko zmienione ustawienia.

Builder nakłada znak wodny po przetworzeniu obrazu, jeśli wywołasz `watermark()`. Możesz przekazać lokalny plik z parametrami, same parametry albo użyć domyślnego pliku z ustawieniami.

```php
$url = sGallery::file($item->src)
    ->fit('crop', 800, 800)
    ->watermark('assets/images/mark.png', ['width' => '20%', 'opacity' => 80])
    ->getFile();
```

Domyślny katalog obrazów wynikowych to `assets/cache/`. Po zmianie `imageCacheDir` uruchom `php artisan vendor:publish --tag=sGallery` z katalogu `core`. Czyszczenie pamięci podręcznej w panelu usuwa zindeksowane pliki wynikowe sGallery i zachowuje oryginały. Listę formatów znajdziesz w [dokumentacji referencyjnej](./reference.md).
