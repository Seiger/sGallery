---
id: background-image-optimization
title: Bildoptimierung im Hintergrund
slug: /background-image-optimization/
sidebar_position: 7
---

# Bildoptimierung im Hintergrund

## Schnelle und langsame Konvertierungen

sGallery verarbeitet Bilder ohne Cache synchron, wenn der Webprozess genügend Speicher hat. Das Paket setzt standardmäßig ein kooperatives Zeitbudget von fünf Sekunden, abrufbar über `Seiger\sGallery\sGallery::optimizationBudgetSeconds()`. Fertige, nicht leere Cache-Dateien gibt es sofort zurück. Leere Dateien werden neu erzeugt.

Die automatische WebP-Qualitätswahl prüft 15 Werte. `99, 97, 95, 93, 92, 91, 90, 89, 88, 87, 86, 85, 84, 82, 80`. Der erste Wert innerhalb der Zielgröße gewinnt. Passt keiner, bleibt der letzte Kandidat. Ein expliziter Aufruf von `quality()` verwendet genau den angegebenen Wert. Die native Verarbeitung unterstützt den expliziten WebP-Wert 100. Für den konfigurierten Standardwert und die Verarbeitung mit Spatie gilt die Obergrenze 99. Erst eine fertige, nicht leere WebP-Datei wird atomar aus der temporären Datei veröffentlicht.

Das Paket prüft das Budget zwischen Operationen und Kodierungsversuchen. Einen bereits laufenden nativen Encoder kann es dabei nicht unterbrechen. Die erste Verarbeitung kann fünf Sekunden oder das aktuelle PHP-Timeout überschreiten. Ein Eintrag im gemeinsamen Storage markiert die Verarbeitung als langsam. Weitere Anfragen überspringen sie. Unfertige temporäre Dateien werden nicht als Ergebnis ausgeliefert.

## Optionale sTask-Integration

Ohne Spatie verarbeitet sTask unterstützte Operationen über GD oder Imagick. Für animierte GIF- und WebP-Dateien ist Imagick erforderlich. Ist Spatie installiert, verwendet der Worker Spatie.

sTask wird separat installiert. Die Warteschlange benötigt die Laufzeitklassen von sTask, die API `heartbeat()` und einen aktuellen Heartbeat des Runners. Ältere sTask-Versionen ohne diese API erhalten keine Hintergrundaufgaben.

Der Worker `Seiger\sGallery\Workers\ImageOptimizationWorker` hat die ID `sgallery.image-optimization` und die Aktion `optimize`. Die erste verzögerte Aufgabe registriert einen fehlenden Worker als aktiv. Einen vorhandenen deaktivierten Worker müssen Sie im sTask-Manager aktivieren.

Nach Ablauf des Budgets liefert das Frontend die Original-URL. Ist sTask nicht verfügbar, der Heartbeat abgelaufen oder das Einreihen fehlgeschlagen, bleibt das Original verfügbar. Die teure Verarbeitung wird nicht bei jeder Anfrage wiederholt. Ein bereits geöffnetes Bild wechselt nicht von selbst. Nach erfolgreicher Verarbeitung leert der Worker den Seiten- und Blade-View-Cache von Evolution, damit die nächste Anfrage das fertige Bild verwendet. Der Cache abgeleiteter Bilder bleibt erhalten.

## Große Bilder

sGallery schätzt vor dem Dekodieren den Speicher für Original, Ergebnis und Zwischenbilder. Eine feste Grenze in Pixeln gibt es nicht. Die Webanfrage nutzt bis zur Hälfte des freien PHP-Speichers nach einer Reserve von 16 MiB für die Anwendung. Bei `memory_limit=-1` geht die Schätzung zunächst von 128 MiB aus. Reicht der geschätzte Spielraum nicht aus, übergibt sGallery die Aufgabe an sTask und liefert die Original-URL. Das Original bleibt auch verfügbar, wenn die Warteschlange die Aufgabe nicht annimmt. Fertige Ableitungen werden direkt aus dem Cache ausgeliefert.

## Aufgabenidentität und Wiederanlauf

Eine Aufgabe enthält Pfad, Größe, Änderungszeit und SHA-256 des Quellinhalts, das ermittelte Format, Verarbeitungsparameter und die Angaben zur expliziten Qualität. Entfernte Quellen gelangen nicht in die Warteschlange. Wird die Quelle während der Wartezeit ersetzt, verwirft sGallery die alte Aufgabe. Eine nicht blockierende Sperre pro Aufgabe und die Deduplizierung in sTask verhindern Duplikate. Nach einem Fehler oder einem Abschluss ohne brauchbaren Cache wartet sGallery fünf Minuten bis zum erneuten Einreihen. Laufende Aufgaben werden wiederverwendet.

Gespeicherte skalare Geometrieparameter funktionieren mit beiden Verarbeitungswegen. Nach der Metadatennormalisierung durch sTask stellt der Worker die ursprüngliche Reihenfolge der Wasserzeichenparameter wieder her. Damit verwendet die nächste Frontend-Anfrage denselben Cache-Schlüssel. Vor der Veröffentlichung prüft der Worker, ob der Wasserzeicheninhalt verändert wurde.

Der Schlüssel des Derivats enthält den vollständigen Quellpfad und die Quellrevision. Ein Austausch bei gleicher Größe und Änderungszeit liefert deshalb keine alten Bilddaten. Der Hash entsteht auch bei einem Cache-Treffer durch sequenzielles Lesen der Datei. Der Worker prüft die Revision vor und nach der Veröffentlichung und vor dem erfolgreichen Abschluss. Bei einem Wechsel rund um die Veröffentlichung entfernt er nur die gerade geschriebene veraltete Datei. Die neue Revision hat einen anderen Schlüssel. Quelle und frühere gültige Derivate bleiben erhalten.

Ältere Aufgaben mit nur Größe und Änderungszeit werden angenommen, wenn beide Werte übereinstimmen. Danach wird der aktuelle Hash ermittelt. Sie erkennen keinen schon vor der Ausführung erfolgten Austausch mit unveränderten Metadaten, verarbeiten aber den aktuellen Inhalt mit dem aktuellen Schlüssel.

Statusdateien liegen in `storage/sgallery/optimization`. Nutzen Sie einen gemeinsamen Storage über Releases hinweg mit Schreibrechten für Web- und CLI-Benutzer.

## Aufgaben in sTask ausführen

Der reguläre sTask-Runner ruft den Worker im eigenen PHP-Prozess auf. Der Worker braucht Schreibrechte im Bildcache. Vor der Verarbeitung gleicht er die Speicherschätzung erneut mit dem `memory_limit` des Runners ab. Bei `-1` setzt er für die Schätzung zunächst 512 MiB an.

Nach erfolgreicher Prüfung wird das Ergebnis durch atomares Umbenennen der Datei veröffentlicht. sTask protokolliert behandelbare Fehler und fährt mit der nächsten Aufgabe fort. Ein fataler Fehler, der den PHP-Prozess beendet, kann den laufenden Durchgang unterbrechen. Nach einer erfolgreichen Aufgabe wird der Seitencache geleert.

## Prüfung

Führen Sie die Integrationstests mit der Composer-Laufzeit einer installierten Evolution-Instanz, sTask mit Heartbeat-API, GD/WebP und SQLite aus.

```sh
SGALLERY_AUTOLOAD=/path/to/site/core/vendor/autoload.php php tests/image-optimization.php
php tests/optional-stask.php
```
