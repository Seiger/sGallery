---
id: getting-started
title: Premiers pas
slug: /getting-started/
sidebar_position: 2
---

# Premiers pas

## Installation dans Evolution CMS

sGallery 2.x nécessite PHP à partir de 8.4 dans la série 8.x, Evolution CMS à partir de 3.6 dans la série 3.x et Composer. Installez GD ou Imagick pour les miniatures. sTask est facultatif.

À la racine du projet Evolution, exécutez les commandes suivantes.

```bash
cd core
php artisan package:installrequire seiger/sgallery "^2.0"
php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"
php artisan migrate
```

## Première galerie

Ouvrez **Configuration du système → sGallery**, ajoutez un modèle de ressource et enregistrez. Vous pouvez aussi ajouter l’identifiant du modèle au fichier `core/custom/config/seiger/settings/sGallery.php`.

```php
<?php return [5];
```

Ouvrez une ressource qui utilise ce modèle et ajoutez une photo. Le [guide de rédaction](./user-guide.md) explique comment gérer les médias. L’[exemple Blade](./use-in-blade.md) montre comment les afficher sur le site.
