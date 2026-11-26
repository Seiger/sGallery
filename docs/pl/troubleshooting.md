---
id: troubleshooting
title: Rozwiązywanie problemów
sidebar_position: 9
---

# Rozwiązywanie problemów

| Objaw | Co sprawdzić |
| --- | --- |
| Zasób nie ma karty galerii | Sprawdź [identyfikator szablonu w ustawieniach](./configuration.md). |
| Redaktor nie może dodać lub usunąć zdjęcia | Sprawdź [uprawnienie do akcji i dostęp do zasobu](./configuration.md#manager-gallery-permissions). Dla produktu sprawdź też regułę integracji. |
| Na stronie widać oryginał | Sprawdź kodek i żądany format. Przy dużym obrazie lub powolnym przekształceniu sprawdź [proces wykonawczy sTask](./background-image-optimization.md). |
| Plik został odrzucony | Sprawdź typ zawartości, rozszerzenie i dozwolone przesyłanie plików w CMS. W przypadku plików biblioteki sprawdź też jej ustawienia i uprawnienia. |
| Composer zatrzymuje się na PHP 8.6 | Sprawdź, czy używana wersja Evolution zawiera [poprawkę instalacji](./getting-started.md). |
