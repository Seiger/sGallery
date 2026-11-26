---
id: configuration
title: Konfiguration
slug: /configuration/
sidebar_position: 3
---

# Konfiguration

Die Projektkonfiguration liegt in `core/custom/config/seiger/settings/sGallery.php`. In diesem Beispiel erhält Vorlage 5 zwei benannte Blöcke und die Vorlagen 1 und 3 jeweils eine Standardgalerie.

```php
<?php return [
    5 => ['slider', 'top-sales'],
    1,
    3,
];
```

Die Rolle benötigt Zugriff auf die Ressource. Zum Lesen genügt `view_document` oder `edit_document`. Für Änderungen sind `edit_document` und `save_document` erforderlich, beim Löschen zusätzlich `delete_document`. Eigene Elterntypen registrieren ihre Berechtigungsprüfung unter `itemPolicies`.

Der Standardordner für abgeleitete Bilder ist `assets/cache/`. Den Pfad können Sie über `imageCacheDir` festlegen. Die Cache-Funktion im Manager entfernt indizierte Derivate und erhält die Originale.
