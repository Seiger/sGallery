---
id: background-image-optimization
title: Optimisation des images en arrière-plan
slug: /background-image-optimization/
sidebar_position: 7
---

# Optimisation des images en arrière-plan

sGallery transforme les petites images dans la requête web. Le budget de cinq secondes est contrôlé entre les étapes. Il ne peut pas interrompre un encodeur en cours. Une transformation trop longue ou trop gourmande en mémoire peut retourner l’URL de l’original et être confiée à sTask.

## Utiliser sTask

Le runner doit fonctionner et publier un signal récent. Lancez-le depuis `core` dans le CMS :

```bash
php artisan stask:worker
```

sGallery enregistre le worker `sgallery.image-optimization` à la première mise en file. Si ce worker existe et est désactivé, activez-le dans sTask. Le cache d’images et le dossier partagé `storage/sgallery/optimization` doivent être accessibles en écriture.

## Cache et reprise

Une tâche active identique est réutilisée. Après un échec, une nouvelle tentative attend cinq minutes. Une tâche liée à une version obsolète de la source ne peut pas publier son résultat. Le dérivé validé est mis en cache de façon atomique. Les transformations de GIF et WebP animés nécessitent Imagick.
