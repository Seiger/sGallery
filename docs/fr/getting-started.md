---
id: getting-started
title: Premiers pas
slug: /getting-started/
sidebar_position: 2
---

# Premiers pas

## Installation dans Evolution CMS

sGallery 1.9 nécessite PHP à partir de 8.4 dans la série 8.x, Evolution CMS à partir de 3.5 dans la série 3.x et Composer. Installez GD ou Imagick pour les miniatures. Spatie Image et sTask sont facultatifs.

Une installation neuve sous PHP 8.6 a été vérifiée avec [Evolution CMS 3.5.x, commit ea42b18c](https://github.com/evolution-cms/evolution/commit/ea42b18c8bbadbcb9b994c8f8cb61e824398bb17). Si vous utilisez sTask pour traiter les images sous Evolution CMS 3.5.9, passez à la [version 2.3.2](https://github.com/Seiger/sTask/releases/tag/v2.3.2), qui corrige la répétition de la migration.

À la racine du projet Evolution, exécutez les commandes suivantes.

```bash
cd core
php artisan package:installrequire seiger/sgallery "^1.9"
php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"
php artisan migrate
```

## Première galerie

Ouvrez **Configuration du système → sGallery**, ajoutez un modèle de ressource et enregistrez. Vous pouvez aussi ajouter l’identifiant du modèle au fichier `core/custom/config/seiger/settings/sGallery.php`.

```php
<?php return [5];
```

Ouvrez une ressource qui utilise ce modèle et ajoutez une photo. Le [guide de rédaction](./user-guide.md) explique comment gérer les médias. L’[exemple Blade](./use-in-blade.md) montre comment les afficher sur le site.
