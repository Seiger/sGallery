---
id: getting-started
title: Erste Schritte
slug: /getting-started/
sidebar_position: 2
---

# Erste Schritte

## Installation in Evolution CMS

sGallery 1.9 benötigt PHP ab 8.4 innerhalb der 8.x-Reihe, Evolution CMS ab 3.5 innerhalb der 3.x-Reihe und Composer. Installieren Sie GD oder Imagick für Vorschaubilder. Spatie Image und sTask sind optional.

Die Neuinstallation mit PHP 8.6 wurde mit [Evolution CMS 3.5.x, Commit ea42b18c](https://github.com/evolution-cms/evolution/commit/ea42b18c8bbadbcb9b994c8f8cb61e824398bb17) geprüft. Wenn Sie sTask mit Evolution CMS 3.5.9 für die Bildverarbeitung verwenden, aktualisieren Sie sTask auf [2.3.2](https://github.com/Seiger/sTask/releases/tag/v2.3.2). Diese Version korrigiert die wiederholte Migration.

Führen Sie im Stammverzeichnis des Evolution-Projekts diese Befehle aus.

```bash
cd core
php artisan package:installrequire seiger/sgallery "^1.9"
php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"
php artisan migrate
```

## Erste Galerie

Öffnen Sie **Systemkonfiguration → sGallery**, weisen Sie eine Ressourcenvorlage zu und speichern Sie. Alternativ können Sie die Vorlagen-ID in die Datei `core/custom/config/seiger/settings/sGallery.php` eintragen.

```php
<?php return [5];
```

Öffnen Sie eine Ressource mit dieser Vorlage und fügen Sie ein Bild hinzu. Das [Redaktionshandbuch](./user-guide.md) erläutert die Arbeit mit Medien. Das [Blade-Beispiel](./use-in-blade.md) zeigt die Ausgabe auf der Website.
