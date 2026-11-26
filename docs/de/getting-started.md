---
id: getting-started
title: Erste Schritte
slug: /getting-started/
sidebar_position: 2
---

# Erste Schritte

sGallery 1.9 benötigt PHP ab 8.4 innerhalb der 8.x-Reihe, Evolution CMS ab 3.5 innerhalb der 3.x-Reihe und Composer. Für die Bildverarbeitung ist GD oder Imagick erforderlich. Eine frische Installation unter PHP 8.6 wurde mit [Evolution CMS 3.5.x](https://github.com/evolution-cms/evolution/commit/ea42b18c8bbadbcb9b994c8f8cb61e824398bb17) geprüft. Für sTask auf Evolution CMS 3.5.9 steht die korrigierte Migration in [sTask 2.3.2](https://github.com/Seiger/sTask/releases/tag/v2.3.2) bereit.

Führen Sie im Projektverzeichnis aus:

```bash
cd core
php artisan package:installrequire seiger/sgallery "^1.9"
php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"
php artisan migrate
```

Weisen Sie unter **Systemkonfiguration → sGallery** eine Ressourcenvorlage zu und speichern Sie. Öffnen Sie eine Ressource mit dieser Vorlage und fügen Sie das erste Bild hinzu.
