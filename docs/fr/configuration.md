---
id: configuration
title: Configuration
slug: /configuration/
sidebar_position: 3
---

# Configuration

Les réglages du projet sont dans `core/custom/config/seiger/settings/sGallery.php`. L’exemple affecte deux blocs nommés au modèle 5 et la galerie standard aux modèles 1 et 3.

```php
<?php return [
    5 => ['slider', 'top-sales'],
    1,
    3,
];
```

La lecture demande l’accès à la ressource et `view_document` ou `edit_document`. Les modifications demandent `edit_document` et `save_document`, avec `delete_document` en plus pour la suppression. Pour un type parent personnalisé, définissez sa politique dans `itemPolicies`.

Le dossier des images générées est `assets/cache/` par défaut. La clé `imageCacheDir` permet de le changer. Le nettoyage dans le gestionnaire retire les dérivés indexés et préserve les originaux.
