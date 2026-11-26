---
id: configuration
title: Konfiguration
slug: /configuration/
sidebar_position: 3
---

# Konfiguration

## Vorlagen und Blöcke

Der Manager speichert die Einstellungen in `core/custom/config/seiger/settings/sGallery.php`. Fehlt die Datei, gelten die Vorgaben aus `config/sGallerySettings.php` im Paket. Die Projekteinstellungen ersetzen die standardmäßigen Vorlagenzuordnungen.

```php
<?php return [
    5 => ['slider', 'top-sales'],
    1,
    3,
];
```

Vorlage 5 erhält zwei benannte Registerkarten, die Vorlagen 1 und 3 die Standardgalerie. Setzen Sie benannte Zuordnungen vor einzelne IDs, um doppelte numerische Array-Schlüssel zu vermeiden. Das ältere verschachtelte Format `[1, 3, [5 => ['slider']]]` wird weiterhin gelesen. Wenn Sie die sGallery-Einstellungen ändern und speichern, schreibt der Manager das flache Format.

## Manager-Berechtigungen {#manager-gallery-permissions}

Galerieaktionen erfordern eine Manager-Sitzung und Zugriff auf die Ressource. Zum Lesen brauchen Sie `view_document` oder `edit_document`. Zum Hinzufügen, Sortieren und Bearbeiten von Übersetzungen sind `edit_document` und `save_document` erforderlich. Zum Löschen benötigen Sie zusätzlich `delete_document`. Der Standardblock heißt `1`.

Registrieren Sie für einen eigenen Typ wie `product` eine Prüfung in `itemPolicies`.

```php
'itemPolicies' => [
    'product' => [\App\Gallery\ProductPolicy::class, 'allows'],
],
```

Die Richtlinie erhält `($action, $parentId, $block, $evolution)` und gibt für eine erlaubte Aktion `true` zurück. Die Aktionen sind `read`, `upload`, `uploadDownload`, `register`, `addYoutube`, `reorder`, `translate` und `delete`. Ohne Richtlinie steht der eigene Typ Administratoren zur Verfügung.

Dateien aus der Bibliothek erfordern `file_manager` oder die passende Berechtigung `assets_images` beziehungsweise `assets_files`. Die Datei muss in `rb_base_dir` und, falls gesetzt, auch in `image_base_upload_dir` liegen. Beim Entfernen der Verknüpfung bleibt das Original in der Bibliothek erhalten.

## Formate, Wasserzeichen und Cache {#watermarks}

Unter **Systemkonfiguration → sGallery** wählen Sie Format, Qualität, Schärfe, Optimierung, Cache-Verzeichnis und Standardwerte für das Wasserzeichen. Beim Speichern werden nur geänderte Einstellungen geschrieben.

Builder wendet das Wasserzeichen am Ende der Bildverarbeitung an, wenn `watermark()` aufgerufen wurde. Sie können eine lokale Datei und Optionen, nur Optionen oder die Standarddatei mit den Standardwerten verwenden.

```php
$url = sGallery::file($item->src)
    ->fit('crop', 800, 800)
    ->watermark('assets/images/mark.png', ['width' => '20%', 'opacity' => 80])
    ->getFile();
```

Das Standardverzeichnis für abgeleitete Bilder ist `assets/cache/`. Führen Sie nach einer Änderung von `imageCacheDir` im Verzeichnis `core` den Befehl `php artisan vendor:publish --tag=sGallery` aus. Die Cache-Bereinigung im Manager entfernt die indexierten sGallery-Derivate und erhält die Originale. Die unterstützten Formate stehen in der [Referenz](./reference.md).
