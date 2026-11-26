---
id: background-image-optimization
title: Optymalizacja obrazów w tle
slug: /background-image-optimization/
sidebar_position: 7
---

# Optymalizacja obrazów w tle

## Szybkie i powolne przekształcenia

sGallery przetwarza obrazy spoza pamięci podręcznej synchronicznie, o ile proces WWW ma wystarczająco dużo pamięci. Pakiet wyznacza domyślny, kooperacyjny limit pięciu sekund. Jego wartość udostępnia `Seiger\sGallery\sGallery::optimizationBudgetSeconds()`. Gotowy, niepusty plik z pamięci podręcznej jest zwracany od razu. Puste pliki są generowane ponownie.

Automatyczny dobór jakości WebP sprawdza 15 wartości. `99, 97, 95, 93, 92, 91, 90, 89, 88, 87, 86, 85, 84, 82, 80`. Wybierana jest pierwsza wartość mieszcząca się w docelowym rozmiarze. Jeśli żadna nie mieści się w limicie, wybierana jest ostatnia. Jawne wywołanie `quality()` używa jednej określonej wartości. Natywne przetwarzanie obsługuje jawną jakość WebP 100. Jakość domyślna z konfiguracji i przetwarzanie przez Spatie mają limit 99. Gotowy, niepusty WebP jest publikowany atomowo z pliku tymczasowego.

Pakiet sprawdza limit między przekształceniami i próbami kodowania. Nie przerywa już uruchomionego natywnego kodera. Pierwsze przetwarzanie może przekroczyć pięć sekund lub bieżący limit czasu PHP. Współdzielona przestrzeń przechowuje znacznik powolnego przekształcenia. Kolejne żądania pomijają przetwarzanie synchroniczne. Niedokończone pliki tymczasowe nie są udostępniane jako wyniki.

## Opcjonalna integracja z sTask

Bez Spatie sTask wykonuje obsługiwane przekształcenia natywne przez GD lub Imagick. Animowane obrazy GIF i WebP wymagają Imagick. Jeśli Spatie jest zainstalowany, worker używa Spatie.

sTask instaluje się oddzielnie. Dodanie do kolejki wymaga klas wykonawczych sTask, API `heartbeat()` oraz aktualnego sygnału z procesu wykonawczego. Starsze wersje sTask bez tego API nie otrzymują odroczonych zadań.

Worker `Seiger\sGallery\Workers\ImageOptimizationWorker` ma identyfikator `sgallery.image-optimization` i akcję `optimize`. Pierwsze odroczone zadanie rejestruje brakującego workera jako aktywnego. Jeśli istniejący worker jest wyłączony, włącz go w panelu sTask.

Po upływie limitu strona zwraca adres oryginału. Gdy sTask jest niedostępny, sygnał jest nieaktualny lub dodanie do kolejki się nie powiodło, oryginał pozostaje dostępny. Kosztowne przekształcenie nie jest powtarzane przy każdym żądaniu. Już otwarty obraz nie zostaje automatycznie podmieniony. Gdy zadanie się powiedzie, worker czyści pamięć podręczną stron i widoków Blade w Evolution, aby kolejne żądania korzystały z gotowego obrazu. Pamięć podręczna obrazów wynikowych pozostaje bez zmian.

## Duże obrazy

Przed dekodowaniem sGallery szacuje pamięć potrzebną dla oryginału, wyniku i obrazów pośrednich. Nie ma stałego limitu wymiarów w pikselach. Żądanie WWW wykorzystuje do połowy wolnej pamięci PHP po odjęciu rezerwy 16 MiB dla aplikacji. Gdy `memory_limit=-1`, początkowy budżet oszacowania wynosi 128 MiB. Jeśli przekształcenie przekracza dostępny zapas, sGallery przekazuje je do sTask i zwraca adres oryginału. Oryginał jest dostępny również wtedy, gdy kolejka nie przyjmie zadania. Gotowe wyniki są zwracane z pamięci podręcznej.

## Identyfikacja zadań i wznawianie

Zadanie zapisuje ścieżkę źródła, rozmiar, czas modyfikacji i sumę SHA-256 zawartości, wykryty format, parametry przekształcenia oraz ustawienie jawnej jakości. Zdalne źródła nie trafiają do kolejki. Podmiana źródła w trakcie oczekiwania unieważnia starsze zadanie. Nieblokująca blokada dla zadania i deduplikacja sTask zapobiegają duplikatom. Po błędzie lub po zakończeniu bez użytecznego wyniku ponowne dodanie zadania czeka pięć minut. Aktywne zadania są wykorzystywane ponownie.

Zapisane skalarne parametry geometrii działają w obu trybach przetwarzania. Po normalizacji metadanych przez sTask worker przywraca kolejność parametrów znaku wodnego. Gotowy plik i następne żądanie strony mają wtedy ten sam klucz. Przed publikacją worker sprawdza, czy zawartość znaku wodnego się zmieniła.

Klucz pliku wynikowego obejmuje pełną ścieżkę i wersję źródła. Podmiana z tym samym rozmiarem i czasem modyfikacji nie zwraca starych pikseli. Skrót oblicza się przez odczyt strumieniowy, również po znalezieniu pliku w pamięci podręcznej. Worker sprawdza wersję przed publikacją, po niej i przed zgłoszeniem sukcesu. Gdy wykryje podmianę w trakcie publikacji, usuwa właśnie zapisany nieaktualny plik. Nowa wersja ma inny klucz. Źródło i wcześniejsze poprawne pliki pozostają dostępne.

Starsze zadania zawierające tylko rozmiar i czas modyfikacji są przyjmowane, jeśli te wartości są zgodne. Następnie worker zapamiętuje aktualny skrót. Takie zadania nie wykryją wcześniejszej podmiany z tymi samymi metadanymi, ale przetworzą aktualną zawartość pod aktualnym kluczem.

Pliki stanu są w `storage/sgallery/optimization`. Korzystaj ze wspólnej przestrzeni między wdrożeniami i zapewnij prawo zapisu użytkownikom WWW i CLI.

## Wykonywanie zadań w sTask

Standardowy proces wykonawczy sTask uruchamia workera w swoim procesie PHP. Worker wymaga prawa zapisu w pamięci podręcznej obrazów. Przed przetwarzaniem ponownie porównuje oszacowanie pamięci z `memory_limit` procesu wykonawczego. Dla wartości `-1` początkowy budżet oszacowania wynosi 512 MiB.

Po sprawdzeniu plik wynikowy jest publikowany przez atomową zmianę nazwy. sTask zapisuje błędy, które potrafi obsłużyć, i przechodzi do następnego zadania. Błąd krytyczny kończący proces PHP może przerwać bieżący przebieg. Po pomyślnym zadaniu czyszczona jest pamięć podręczna stron.

## Weryfikacja

Uruchom testy integracyjne z zależnościami Composera z zainstalowanej instancji Evolution, sTask z API sygnału, obsługą GD i WebP oraz SQLite.

```sh
SGALLERY_AUTOLOAD=/path/to/site/core/vendor/autoload.php php tests/image-optimization.php
php tests/optional-stask.php
```
