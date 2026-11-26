---
id: developer-guide
title: Entwicklerhandbuch
sidebar_position: 6
---

# Entwicklerhandbuch

Mit `collections()` lesen Sie die Galerie. In eigenen Integrationen wählen Sie Eltern-ID, Typ und Block. Registrieren Sie für eigene Typen eine [Aktions- und Zugriffsprüfung](./configuration.md). Beispiele für die sichere Ausgabe stehen unter [Blade](./use-in-blade.md).

```php
$url = sGallery::file($item->src)
    ->fit('max', 800, 600)
    ->format('webp')
    ->getFile();
```

`getFile()` liefert die Ergebnis-URL. Benötigt ein Bild zu viel Zeit oder Speicher, kann ein verfügbarer sTask-Runner das Derivat später erstellen.
