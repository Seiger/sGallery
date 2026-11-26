---
id: developer-guide
title: Guide du développeur
sidebar_position: 6
---

# Guide du développeur

Utilisez `collections()` pour parcourir les entrées. Pour une intégration, sélectionnez le type, l’identifiant du parent, le bloc et la langue. Associez aux types personnalisés une [politique d’accès](./configuration.md). Voir les [exemples Blade](./use-in-blade.md).

```php
$url = sGallery::file($item->src)
    ->fit('max', 800, 600)
    ->format('webp')
    ->getFile();
```

`getFile()` retourne l’URL générée. Un runner sTask disponible peut prendre en charge les images trop longues à traiter dans la requête web.
