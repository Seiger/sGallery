# Виправлення sGallery після E2E-перевірки — 22.11.2026

**E2E-02–E2E-04 виправлено в sGallery.** Перевірено робочі зміни на базі
`d425a36f113b2ce5a897386e62fed94ab787ab61` у стенді Evolution CMS 3.5.9.
Коміт не створювався; попередні незакомічені зміни збережено.

## Зміни

- Початкова міграція створює лише відсутні таблиці, незалежно одна від одної.
  Наявні `s_galleries` та `s_gallery_fields` не замінюються. Пізніші міграції
  як і раніше додають `block` та перейменовують `resource_type` за потреби.
- `GalleryMigration::isInstallerBaseline()` читає окрему історію
  `migrations_install`. Rollback трьох міграцій sGallery не видаляє таблиці
  чи колонки, уже створені інсталятором. Для таблиць, створених звичайними
  application-міграціями без такого baseline, збережено звичайний rollback.
  Історія інших пакетів не переноситься й не змінюється.
- Обидва upload користуються необов'язковим loader: його відсутність або
  недоступний parent document не блокують запит. При завершенні, помилці й
  порожньому виборі спільний helper ховає наявний індикатор.
- Delete повертає наявний переклад `sGallery::manager.deleted` замість
  відсутнього `deleted_successfully`. Російський текст цього ключа виправлено
  на `Удалено`; англійський — `Deleted`, український — `Видалено`.

## Перевірки

| Перевірка | Результат |
| --- | --- |
| Повний runtime-набір, PHP 8.4.28/8.5.13/8.6.0 × native/Spatie | 180/180 прогонів |
| Реальний CMS: три PHP × clean/native, clean/Spatie, upgrade/native | 9/9 клітинок міграцій |
| migrate → migrate → rollback → migrate → migrate лише для sGallery | 45/45 команд, без SQL-помилок |
| Браузер: три PHP × native/Spatie × shell/окреме вікно | 6/6 прогонів, 12/12 контекстів |
| Gallery audit та PHP syntax | 3/3 набори на трьох PHP |

У кожній із дев'яти CMS-клітинок порівняно записи галереї, переклади, чергу,
worker та історію інсталятора. Схема `s_galleries` і SHA-256 усіх файлів
`assets` після кожного кроку збережені. Команди обмежено шляхом міграцій
sGallery, а не міграціями сторонніх пакетів. Клітинки — окремі копії SQLite;
живі бази стенду для rollback не використовувалися.

Новий `migrations-test.php` додатково виконує справжній Illuminate Migrator
над SQLite з префіксом `evo_`: чиста схема, повторний migrate, нормальний
rollback, повторна установка, adoption інсталятора з даними, rollback
installer baseline, старий `resource_type`, обидва варіанти часткової
установки й сторонній запис installer history. `manager-message-test.php`
викликає реальний delete-контролер та FileLoader/Translator для en/uk/ru.

Браузерний `manager-regressions.mjs` перевіряє PNG і PDF upload у shell та
окремій формі ресурсу, порожній вибір, 422 для фальшивого PNG, стан loader,
переклад відповіді видалення, HTTP 404 власного видаленого оригіналу та
збереження попередніх записів галереї. Downloads-input додається harness за
контрактом `sectionDownloads`, бо звичайна resource-вкладка показує media-input.
Використано справжні сесію, CSRF, HTTP-контролери та делегований change listener.
MCP виконав тіло тієї самої функції з байтами PNG-фікстури; syntax модуля
перевірено окремо. Нових Composer/npm-залежностей не додано.

[JSON перевірки](sgallery-e2e-fixes-2026-11-22.json) містить відбитки семи
production-файлів, версії PHP, міграційні клітинки й результати браузера.
Основні журнали: `%TEMP%/sgallery-fix190-php{84,85,86}-{clean,spatie}-runtime.log`
та `sgallery-fix190-php{84,85,86}-migrations.log`.
Деталі міграцій у volume `sgallery-e2e-20261122-d425a36`:
`/lab/cases/fix190-phpXX-{clean,spatie,upgrade}/migration-fix-result.json`.
Стенд отримав поточні src/views/database/lang; Composer autoload і compiled
views оновлено лише в ізольованих установках.

## Межі та залишкові питання

- **sTask:** після успішного застосування sGallery звичайний глобальний
  `artisan migrate` натрапляє на `create_task_tables` та наявну `s_workers`.
  Це відтворено в окремій копії; журнал —
  `%TEMP%/sgallery-fix190-global-migrate.log`. Код і міграції sTask не змінено.
  Успішні цільові міграції sGallery не оголошуються успішним global migrate.
- **Evolution 3.5.9 / Composer:** попередній E2E-01 з Pest/ParaTest на PHP 8.6
  залишається відкритим. Залежності стенду встановлено на 8.4, runtime —
  стабільний 8.6.0. Ця зміна не виправляє manifest CMS.
- **JS ядра:** при unload окремої форми CMS 3.5.9 звертається до відсутнього
  `top.mainMenu.work()` у `manager/media/script/main.js:426`. Це повідомлення
  з точним stack окремо записано в `coreUnloadNotices`; інші `pageerror`
  лишаються помилкою тесту. Upload завершуються успішно до unload.
- Відомий PHP 8.6 `mb_split()` deprecation саме з Illuminate окремо записано
  новими runtime-тестами; решта попереджень залишаються строгими.
- Перевірено SQLite, локальний PHP server і Chromium через Playwright.
  Production та інші СУБД у цьому прогоні не перевірялися.

Три зауваження sGallery закрито за цією матрицею. Повна готовність релізу
залишається окремим рішенням через названі проблеми CMS/sTask.

Назва коміта для цих виправлень:
`fix(compat): preserve gallery data and standalone uploads`.
