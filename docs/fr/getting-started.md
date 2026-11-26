---
id: getting-started
title: Premiers pas
slug: /getting-started/
sidebar_position: 2
---

# Premiers pas

sGallery 1.9 requiert PHP 8.4 ou une version ultérieure de la série 8.x, Evolution CMS 3.5 ou une version ultérieure de la série 3.x, et Composer. Installez GD ou Imagick pour les vignettes. Une installation neuve sous PHP 8.6 a été vérifiée avec [Evolution CMS 3.5.x](https://github.com/evolution-cms/evolution/commit/ea42b18c8bbadbcb9b994c8f8cb61e824398bb17). Sous Evolution CMS 3.5.9, [sTask 2.3.2](https://github.com/Seiger/sTask/releases/tag/v2.3.2) corrige la répétition de ses migrations.

Depuis la racine du projet Evolution :

```bash
cd core
php artisan package:installrequire seiger/sgallery "^1.9"
php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"
php artisan migrate
```

Dans **Configuration du système → sGallery**, affectez un modèle et enregistrez. Ouvrez une ressource qui utilise ce modèle et ajoutez une image.
