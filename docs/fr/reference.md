---
id: reference
title: Référence
sidebar_position: 8
---

# Référence

## Formats

La prise en charge dépend des codecs de l’extension PHP installée.

| Format | GD | Imagick |
| --- | --- | --- |
| JPEG, PNG, GIF, WebP, AVIF | Lecture et écriture | Lecture et écriture |
| BMP | Lecture | Lecture |
| TIFF à une seule page | | Lecture |
| HEIC / HEIF statique | | Lecture avec Fileinfo et un décodeur HEIF |
| JPEG XL statique | | Lecture avec un décodeur JPEG XL |

Imagick est nécessaire pour transformer les GIF et WebP animés. Si un codec ou une opération est indisponible, le Builder peut renvoyer l’URL de l’original. `format('original')` conserve l’extension si un encodeur est disponible pour ce format.

## Méthodes du Builder

| Méthode | Usage |
| --- | --- |
| `collections()`, `documentId()`, `itemType()`, `blockName()`, `lang()`, `get()` | Sélectionner les entrées par ressource, bloc et langue |
| `file()`, `getFile()` | Ouvrir une image source et obtenir l’URL du résultat |
| `resize()`, `fit()`, `crop()` | Définir les dimensions, l’ajustement ou le recadrage |
| `manualCrop()`, `focalCrop()` | Sélectionner une zone à partir des coordonnées de la source |
| `format()`, `quality()`, `optimize()` | Régler le format, la qualité et l’optimisation |
| `watermark()`, `sharpen()` | Appliquer un filigrane ou régler la netteté |

Les [guides de traitement des images](./manipulations/index.md) donnent des exemples détaillés.
