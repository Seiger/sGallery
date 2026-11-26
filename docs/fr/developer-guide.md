---
id: developer-guide
title: Guide de développement
sidebar_position: 6
---

# Guide de développement

## Lire une galerie

`collections()` renvoie un Builder. `itemType()`, `documentId()` et `blockName()` sélectionnent l’enregistrement parent et la galerie. `lang()` précise la langue si nécessaire. Le [guide Blade](./use-in-blade.md) fournit des exemples pour les ressources et les produits.

Pour un type personnalisé, utilisez le même type et le même bloc dans le gestionnaire et sur le site. La [règle d’accès](./configuration.md#manager-gallery-permissions) doit vérifier l’autorisation d’exécuter l’action et l’accès à l’enregistrement parent désigné.

Un modèle de module peut afficher le formulaire du gestionnaire fourni par le paquet.

```blade
{!! sGallery::initialiseView()->viewType('section')->itemType('product')->idType('i')->blockName('photo') !!}
```

`idType()` désigne le champ de la requête contenant l’identifiant du parent. Les vues disponibles sont `tab`, `section` et `sectionDownloads`. Si sGallery est une intégration facultative, vérifiez `evo()->bound('sGallery')` avant d’afficher le formulaire.

## Traiter une image

```php
$url = sGallery::file($item->src)
    ->fit('max', 800, 600)
    ->format('webp')
    ->getFile();
```

Le Builder choisit automatiquement GD ou Imagick selon les formats source et cible. Consultez les [guides de traitement des images](./manipulations/index.md) pour les transformations et l’[optimisation en arrière-plan](./background-image-optimization.md) pour les images volumineuses ou lentes à traiter.
