---
id: getting-started
title: Pierwsze kroki
slug: /getting-started/
sidebar_position: 2
---

# Pierwsze kroki

sGallery 1.9 wymaga PHP od 8.4 w serii 8.x, Evolution CMS od 3.5 w serii 3.x oraz Composera. Do generowania miniatur zainstaluj GD lub Imagick. Czystą instalację na PHP 8.6 sprawdzono z [Evolution CMS 3.5.x](https://github.com/evolution-cms/evolution/commit/ea42b18c8bbadbcb9b994c8f8cb61e824398bb17). Dla Evolution CMS 3.5.9 poprawka powtórnej migracji jest dostępna w [sTask 2.3.2](https://github.com/Seiger/sTask/releases/tag/v2.3.2).

W katalogu głównym projektu wykonaj:

```bash
cd core
php artisan package:installrequire seiger/sgallery "^1.9"
php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"
php artisan migrate
```

W ustawieniach systemu otwórz zakładkę **sGallery**, przypisz szablon i zapisz. Następnie otwórz zasób z tym szablonem i dodaj zdjęcie.
