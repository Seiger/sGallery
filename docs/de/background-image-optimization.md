---
id: background-image-optimization
title: Bildoptimierung im Hintergrund
slug: /background-image-optimization/
sidebar_position: 7
---

# Bildoptimierung im Hintergrund

sGallery erzeugt kleine Derivate normalerweise im Webrequest. Das Zeitbudget von fünf Sekunden wird zwischen den Verarbeitungsschritten geprüft. Ein bereits laufender Encoder lässt sich dadurch nicht unterbrechen. Wenn die Verarbeitung zu lange dauert oder den geschätzten Speicher überschreitet, bleibt die Original-URL verfügbar.

## sTask verwenden

Ein geplanter sTask-Runner muss aktiv sein und einen aktuellen Heartbeat hinterlassen. Starten Sie den Runner aus dem CMS-Verzeichnis `core`:

```bash
php artisan stask:worker
```

sGallery registriert den fehlenden Worker `sgallery.image-optimization` beim ersten Auftrag. Wenn er bereits deaktiviert ist, aktivieren Sie ihn im sTask-Manager. Der Runner benötigt Schreibrechte für den Bildcache und das gemeinsame Verzeichnis `storage/sgallery/optimization`.

## Cache und erneute Versuche

Aktive Aufträge für dieselbe Quelle und dieselben Parameter werden wiederverwendet. Nach einem Fehler greift eine erneute Wartezeit von fünf Minuten. Bei geänderten Quelldaten kann ein älterer Auftrag sein Ergebnis nicht veröffentlichen. Ein erfolgreiches Derivat wird nach Prüfung atomar abgelegt. Für animierte GIF- oder WebP-Bilder ist Imagick erforderlich.
