---
id: getting-started
title: Pierwsze kroki
slug: /getting-started/
sidebar_position: 2
---

# Pierwsze kroki

## Instalacja w Evolution CMS

sGallery 1.9 wymaga PHP od 8.4 w serii 8.x, Evolution CMS od 3.5 w serii 3.x oraz Composera. Aby generować miniatury, zainstaluj GD lub Imagick. Spatie Image i sTask są opcjonalne.

Czystą instalację na PHP 8.6 sprawdzono z [Evolution CMS 3.5.x w commicie ea42b18c](https://github.com/evolution-cms/evolution/commit/ea42b18c8bbadbcb9b994c8f8cb61e824398bb17). Jeśli używasz sTask do przetwarzania obrazów w Evolution CMS 3.5.9, zaktualizuj go do [wersji 2.3.2](https://github.com/Seiger/sTask/releases/tag/v2.3.2), która naprawia ponowne wykonanie migracji.

Uruchom poniższe polecenia w katalogu głównym projektu Evolution.

```bash
cd core
php artisan package:installrequire seiger/sgallery "^1.9"
php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"
php artisan migrate
```

## Pierwsza galeria

Otwórz **Konfiguracja systemu → sGallery**, dodaj szablon zasobu i zapisz ustawienia. Możesz też dopisać identyfikator szablonu do pliku `core/custom/config/seiger/settings/sGallery.php`.

```php
<?php return [5];
```

Otwórz zasób korzystający z tego szablonu i dodaj zdjęcie. [Poradnik redaktora](./user-guide.md) opisuje obsługę multimediów, a [przykład Blade](./use-in-blade.md) pokazuje, jak je wyświetlić na stronie.
