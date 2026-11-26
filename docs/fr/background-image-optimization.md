---
id: background-image-optimization
title: Optimisation des images en arrière-plan
slug: /background-image-optimization/
sidebar_position: 7
---

# Optimisation des images en arrière-plan

## Transformations rapides et lentes

sGallery traite les images hors cache de façon synchrone si la mémoire disponible pour le processus web suffit. Le paquet fixe un budget de temps coopératif de cinq secondes, consultable via `Seiger\sGallery\sGallery::optimizationBudgetSeconds()`. Un fichier de cache complet et non vide est renvoyé immédiatement. Un fichier vide est régénéré.

Le choix automatique de la qualité WebP essaie 15 valeurs. `99, 97, 95, 93, 92, 91, 90, 89, 88, 87, 86, 85, 84, 82, 80`. Le premier candidat qui respecte la taille cible est retenu. Si aucun ne convient, le dernier est utilisé. Un appel explicite à `quality()` utilise uniquement la valeur demandée. Le traitement natif accepte la qualité WebP 100 si elle est demandée explicitement. La qualité par défaut est plafonnée à 99. Un WebP complet et non vide est publié de façon atomique à partir du fichier temporaire.

Le budget est vérifié entre les opérations et les essais d’encodage. Il ne peut pas interrompre un encodeur natif en cours. Le premier traitement peut dépasser cinq secondes ou le délai PHP configuré. Le stockage partagé conserve un indicateur de transformation lente. Les requêtes suivantes ignorent le traitement synchrone. Un fichier temporaire incomplet ne peut pas être servi comme résultat.

## Intégration facultative de sTask

sTask exécute les transformations prises en charge via GD ou Imagick. Les GIF et WebP animés requièrent Imagick.

sTask s’installe séparément. La mise en file nécessite ses classes d’exécution, l’API `heartbeat()` et un signal récent du runner. Les anciennes versions de sTask dépourvues de cette API ne reçoivent aucune tâche différée.

Le worker `Seiger\sGallery\Workers\ImageOptimizationWorker` porte l’identifiant `sgallery.image-optimization` et l’action `optimize`. La première tâche différée crée un worker actif s’il est absent. S’il existe mais est désactivé, activez-le dans le gestionnaire sTask.

Une fois le budget épuisé, le site renvoie l’URL de l’original. Si sTask est indisponible, si le signal est périmé ou si la mise en file échoue, l’original demeure accessible. La transformation coûteuse n’est pas refaite à chaque requête. Une page déjà ouverte ne remplace pas automatiquement l’image. Après une tâche réussie, le worker purge les caches de pages et des vues Blade d’Evolution pour que les prochaines requêtes utilisent le dérivé. Le cache des images dérivées est conservé.

## Grandes images

Avant le décodage, sGallery estime la mémoire nécessaire pour l’original, le dérivé et les images intermédiaires. Il n’impose aucune limite fixe en pixels. La requête web utilise jusqu’à la moitié de la mémoire PHP disponible après une réserve de 16 Mio pour l’application. Avec `memory_limit=-1`, l’estimation part d’un budget de 128 Mio. Si la transformation dépasse le budget, sGallery la confie à sTask et renvoie l’URL de l’original. Ce dernier reste disponible même si la file refuse la tâche. Les dérivés prêts sont servis depuis le cache.

## Identité des tâches et reprise

Une tâche enregistre le chemin de la source, sa taille, sa date de modification et le SHA-256 de son contenu, le format détecté, les paramètres de transformation et la qualité explicite. Les sources distantes ne sont pas mises en file. Un remplacement de la source pendant l’attente invalide l’ancienne tâche. Un verrou non bloquant par tâche et la déduplication de sTask évitent les doublons. Après un échec ou une fin sans cache utilisable, la remise en file attend cinq minutes. Les tâches actives sont réutilisées.

Les paramètres scalaires de géométrie enregistrés fonctionnent dans les deux modes de traitement. Après la normalisation sTask, le worker rétablit l’ordre des options de filigrane. Le résultat et la requête suivante partagent ainsi la même clé de cache. Le worker vérifie avant publication si le contenu du filigrane a changé.

La clé du dérivé inclut le chemin complet et la révision de la source. Un remplacement conservant la taille et la date de modification ne renvoie pas d’anciens pixels. L’empreinte est calculée par lecture en flux, même si le cache contient un résultat. Le worker vérifie la révision avant et après la publication, puis avant de signaler le succès. Si la source change au moment de la publication, il retire le résultat périmé qu’il vient d’écrire. La nouvelle révision utilise une autre clé. La source et les anciens dérivés valides sont préservés.

Les anciennes tâches qui ne contiennent que la taille et la date de modification sont acceptées si ces valeurs correspondent. Le worker fixe ensuite l’empreinte actuelle. Il ne peut pas détecter un changement antérieur à l’exécution si ces valeurs sont identiques, mais il traite les octets actuels sous la clé actuelle.

Les fichiers d’état se trouvent dans `storage/sgallery/optimization`. Partagez ce stockage entre les versions déployées et donnez un accès en écriture aux utilisateurs web et CLI.

## Exécution des tâches par sTask

Le runner sTask standard appelle le worker dans son propre processus PHP. Le worker doit pouvoir écrire dans le cache des images. Avant le traitement, il revérifie l’estimation de mémoire selon le `memory_limit` du runner. Si la valeur est `-1`, l’estimation part de 512 Mio.

Après validation, le dérivé est publié par renommage atomique. sTask enregistre les erreurs qu’il peut traiter et passe à la tâche suivante. Une erreur fatale qui arrête le processus PHP peut interrompre l’exécution en cours. Le cache des pages est purgé après une tâche réussie.

## Vérification

Exécutez les tests d’intégration avec l’environnement Composer d’une installation Evolution, sTask avec son API de signal de vie, GD avec WebP et SQLite.

```sh
SGALLERY_AUTOLOAD=/path/to/site/core/vendor/autoload.php php tests/image-optimization.php
php tests/optional-stask.php
```
