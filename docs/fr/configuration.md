---
id: configuration
title: Configuration
slug: /configuration/
sidebar_position: 3
---

# Configuration

## Modèles et blocs

Le gestionnaire enregistre les paramètres dans `core/custom/config/seiger/settings/sGallery.php`. En l’absence de ce fichier, il utilise les valeurs par défaut de `config/sGallerySettings.php` dans le paquet. Les paramètres du projet remplacent les associations de modèles par défaut.

```php
<?php return [
    5 => ['slider', 'top-sales'],
    1,
    3,
];
```

Le modèle 5 reçoit deux onglets nommés. Les modèles 1 et 3 reçoivent la galerie standard. Placez les associations nommées avant les identifiants seuls pour éviter les clés numériques en double dans le tableau. L’ancienne forme imbriquée `[1, 3, [5 => ['slider']]]` reste lisible. Après modification et enregistrement des paramètres de sGallery, le gestionnaire écrit la forme à plat.

## Autorisations dans le gestionnaire {#manager-gallery-permissions}

Les actions de galerie exigent une session dans le gestionnaire et un accès à la ressource. La lecture requiert `view_document` ou `edit_document`. L’ajout, le tri et la traduction nécessitent `edit_document` et `save_document`. La suppression requiert aussi `delete_document`. Le bloc standard se nomme `1`.

Pour un type personnalisé comme `product`, enregistrez une vérification dans `itemPolicies`.

```php
'itemPolicies' => [
    'product' => [\App\Gallery\ProductPolicy::class, 'allows'],
],
```

La règle reçoit `($action, $parentId, $block, $evolution)` et renvoie `true` si l’action est autorisée. Les actions sont `read`, `upload`, `uploadDownload`, `register`, `addYoutube`, `reorder`, `translate` et `delete`. Sans règle, le type personnalisé est accessible aux administrateurs.

L’ajout d’un fichier de la bibliothèque nécessite `file_manager` ou l’autorisation correspondante `assets_images` ou `assets_files`. Le fichier doit se trouver dans `rb_base_dir` et dans `image_base_upload_dir` si ce dernier est défini. Le retrait de la référence conserve l’original dans la bibliothèque.

## Formats, filigranes et cache {#watermarks}

Dans **Configuration du système → sGallery**, choisissez le format et la qualité des images, la netteté, l’optimisation, le répertoire de cache et les paramètres de filigrane par défaut. L’enregistrement écrit uniquement les paramètres modifiés.

Le Builder applique le filigrane après le traitement quand vous appelez `watermark()`. Vous pouvez fournir un fichier local et des options, uniquement des options, ou utiliser le fichier et les options par défaut.

```php
$url = sGallery::file($item->src)
    ->fit('crop', 800, 800)
    ->watermark('assets/images/mark.png', ['width' => '20%', 'opacity' => 80])
    ->getFile();
```

Le répertoire par défaut des images dérivées est `assets/cache/`. Après modification de `imageCacheDir`, exécutez `php artisan vendor:publish --tag=sGallery` depuis `core`. Le nettoyage du cache dans le gestionnaire supprime les dérivés indexés par sGallery et préserve les originaux. Consultez la [référence](./reference.md) pour la liste des formats.
