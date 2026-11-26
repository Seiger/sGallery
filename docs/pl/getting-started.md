---
id: getting-started
title: Pierwsze kroki
slug: /getting-started/
sidebar_position: 2
---

# Pierwsze kroki

## Instalacja w Evolution CMS

sGallery 2.x wymaga PHP od 8.4 w serii 8.x, Evolution CMS od 3.6 w serii 3.x oraz Composera. Aby generować miniatury, zainstaluj GD lub Imagick. sTask jest opcjonalny.

Uruchom poniższe polecenia w katalogu głównym projektu Evolution.

```bash
cd core
php artisan package:installrequire seiger/sgallery "^2.0"
php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"
php artisan migrate
```

## Pierwsza galeria

Otwórz **Konfiguracja systemu → sGallery**, dodaj szablon zasobu i zapisz ustawienia. Możesz też dopisać identyfikator szablonu do pliku `core/custom/config/seiger/settings/sGallery.php`.

```php
<?php return [5];
```

Otwórz zasób korzystający z tego szablonu i dodaj zdjęcie. [Poradnik redaktora](./user-guide.md) opisuje obsługę multimediów, a [przykład Blade](./use-in-blade.md) pokazuje, jak je wyświetlić na stronie.
