---
id: getting-started
title: Erste Schritte
slug: /getting-started/
sidebar_position: 2
---

# Erste Schritte

## Installation in Evolution CMS

sGallery 2.x benötigt PHP ab 8.4 innerhalb der 8.x-Reihe, Evolution CMS ab 3.6 innerhalb der 3.x-Reihe und Composer. Installieren Sie GD oder Imagick für Vorschaubilder. sTask ist optional.

Führen Sie im Stammverzeichnis des Evolution-Projekts diese Befehle aus.

```bash
cd core
php artisan package:installrequire seiger/sgallery "^2.0"
php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"
php artisan migrate
```

## Erste Galerie

Öffnen Sie **Systemkonfiguration → sGallery**, weisen Sie eine Ressourcenvorlage zu und speichern Sie. Alternativ können Sie die Vorlagen-ID in die Datei `core/custom/config/seiger/settings/sGallery.php` eintragen.

```php
<?php return [5];
```

Öffnen Sie eine Ressource mit dieser Vorlage und fügen Sie ein Bild hinzu. Das [Redaktionshandbuch](./user-guide.md) erläutert die Arbeit mit Medien. Das [Blade-Beispiel](./use-in-blade.md) zeigt die Ausgabe auf der Website.
