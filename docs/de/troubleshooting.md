---
id: troubleshooting
title: Fehlerbehebung
sidebar_position: 9
---

# Fehlerbehebung

| Symptom | Was Sie prüfen können |
| --- | --- |
| An einer Ressource fehlt die Galerie | Prüfen Sie die [Vorlagen-ID in den Einstellungen](./configuration.md). |
| Ein Redakteur kann kein Bild hinzufügen oder entfernen | Prüfen Sie die [Aktionsberechtigung und den Ressourcenzugriff](./configuration.md#manager-gallery-permissions). Prüfen Sie bei Produkten auch die Integrationsrichtlinie. |
| Die Website zeigt das Original | Prüfen Sie Codec und Ausgabeformat. Bei großen Bildern oder langsamer Verarbeitung prüfen Sie den [sTask-Runner](./background-image-optimization.md). |
| Eine Datei wird abgelehnt | Prüfen Sie Dateityp, Dateiendung und die erlaubten CMS-Uploads. Prüfen Sie bei Bibliotheksdateien auch Bibliothekseinstellungen und Berechtigungen. |
| Composer bricht mit PHP 8.6 ab | Prüfen Sie, ob die Evolution-Version die [Installationskorrektur](./getting-started.md) enthält. |
