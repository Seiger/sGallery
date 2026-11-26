---
id: troubleshooting
title: Dépannage
sidebar_position: 9
---

# Dépannage

| Symptôme | Points à vérifier |
| --- | --- |
| Une ressource n’a pas d’onglet de galerie | Vérifiez l’[identifiant du modèle dans les paramètres](./configuration.md). |
| Un membre de l’équipe de rédaction ne peut pas ajouter ou supprimer une photo | Vérifiez les [autorisations de l’action et l’accès à la ressource](./configuration.md#manager-gallery-permissions). Pour un produit, vérifiez aussi la règle d’intégration. |
| Le site affiche l’original | Vérifiez le codec et le format demandé. Pour une transformation lente ou une grande image, vérifiez le [runner sTask](./background-image-optimization.md). |
| Un fichier est refusé | Vérifiez le type de contenu, l’extension et les téléversements autorisés par le CMS. Pour un fichier de la bibliothèque, vérifiez ses paramètres et les autorisations. |
| Composer s’arrête sous PHP 8.6 | Vérifiez que le CMS intègre le [correctif d’installation](./getting-started.md). |
