---
id: developer-guide
title: Entwicklerhandbuch
sidebar_position: 6
---

# Entwicklerhandbuch

## Galerie lesen

`collections()` gibt einen Builder zurück. Mit `itemType()`, `documentId()` und `blockName()` wählen Sie den übergeordneten Datensatz und die Galerie. `lang()` setzt bei Bedarf die Sprache. Der [Blade-Leitfaden](./use-in-blade.md) enthält Beispiele für Ressourcen und Produkte.

Verwenden Sie für einen eigenen Typ im Manager und im Frontend denselben Typ und Block. Die [Zugriffsrichtlinie](./configuration.md#manager-gallery-permissions) muss sowohl die Berechtigung für die Aktion als auch den Zugriff auf den konkreten übergeordneten Datensatz prüfen.

Ein Modul-Template kann das vorhandene Manager-Formular ausgeben.

```blade
{!! sGallery::initialiseView()->viewType('section')->itemType('product')->idType('i')->blockName('photo') !!}
```

`idType()` bestimmt das Request-Feld mit der ID des übergeordneten Datensatzes. Die verfügbaren Ansichten sind `tab`, `section` und `sectionDownloads`. Wenn sGallery optional eingebunden ist, prüfen Sie vor der Ausgabe `evo()->bound('sGallery')`.

## Bildverarbeitung

```php
$url = sGallery::file($item->src)
    ->fit('max', 800, 600)
    ->format('webp')
    ->getFile();
```

Builder wählt anhand von Quell- und Zielformat automatisch GD oder Imagick. Weitere Einzelheiten finden Sie im [Handbuch zur Bildverarbeitung](./manipulations/index.md). Für langsame oder große Bilder siehe [Hintergrundoptimierung](./background-image-optimization.md).
