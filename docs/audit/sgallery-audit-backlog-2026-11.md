# sGallery: статична звірка історичного аудиту та backlog

Дата: **10.11.2026**, Europe/Kiev. Репозиторій: H:/PhpstormProjects/sGallery.

## Зріз, походження та межі

- Гілка: **main**. Перевірений HEAD: **e82d8680ea1d24e83363c90382c82cc7ac890a38**.
- Початковий git status --porcelain=v1 порожній; дерево чисте. git status --short --branch: main...origin/main. Віддалений стан не оновлювався.
- Застосовано надані користувачем AGENTS.md-інструкції та прочитано H:/PhpstormProjects/MiddleSokil/SokilBook/contracts/WRITING-CONTRACT.md. Окремих AGENTS.md у sGallery та перевірених батьківських каталогах не знайдено. Прочитано README, composer.json, відповідний код, шаблони та вибрані тести як текст.
- У docs/ є сайт документації, але усталеного місця аудитів не знайдено. Використано запитаний docs/audit/. Перед створенням файла перевірено відсутність цільового імені.
- **Архів 06.11.2026 не знайдено в перевірених локальних каталогах**: sGallery, MiddleSokil (також пошук назв у .private), Extras/MiddleSokil, проєктні docs і .codex. Це не твердження про відсутність архіву на всьому комп’ютері. Історичне джерело — **витяг користувача**; повні картки не прочитані.
- Чотири прямі картки sGallery: **C-006, H-019, H-020, M-017**. Історичний CONFIRMED у зрізі 04.11 означав наявність конструкції в тодішньому коді, а не перевірену експлуатацію. Оновлення 06.11 уточнювало посилання й рядки, а не проводило новий аудит.
- Історичні рядки використано лише як орієнтири. Нижче наведено рядки прочитаного локального коду.
- Виконано лише читання файлів/Git-метаданих і створення цього Markdown. Застосунок, workers, міграції, тести, image processing, HTTP-запити, завантаження файлів та експлуатаційні перевірки не запускалися. Commit, push і PR не створювалися.

**Статична конструкція не є доведеною експлуатацією.** Історичні severity/status — поля для простежуваності, а не нова оцінка production-ризику.

### Додаткові локальні джерела

Ці checkout не є встановленим середовищем sGallery. Ними не підмінено production-конфігурацію.

| Позначення | Шлях | Commit / стан |
| --- | --- | --- |
| CORE-A | H:/PhpstormProjects/Extras/evolution | 7d8080b9f09db49cfe0abf8cec3ad3004cfb7f61; чисте дерево |
| EVO330 | Локальний тег 3.3.0 у CORE-A, читання git show без checkout | bbd89a52d70fd64252ad5a6e5bc918a907537600 |
| CORE-B | H:/PhpstormProjects/Extras/MiddleSokil/evolution-shared-browser-36 | 2b566219ef1228ba0af41224a1773016ce62f44f; чисте дерево |
| MF | H:/PhpstormProjects/Extras/MiddleSokil/multifields | efaf5cb887e13142ed2eb61c3ffb5ee875f96c54; чисте дерево |

Без позначення джерела file:line означає файл sGallery. EVO330 означає рядки об’єкта тегу, а не поточного робочого файла. Префікси зовнішніх шляхів задано таблицею. Для пов’язаних карток відсутні у витягу історичні поля не виведено з літери ID.

## Інвентар і послідовність

SG-* — внутрішні записи backlog, а не нові історичні картки.

| Запис | Історичний ID | Історична severity | Історичний статус | Новий висновок |
| --- | --- | --- | --- | --- |
| SG-01 | C-006 | CRITICAL | CONFIRMED | Клієнтське розширення зберігається; є MIME-перевірка та PHP-extension guard залежності |
| SG-02 | C-006 | CRITICAL | CONFIRMED | Download має окремий MIME-список і ту саму схему імені |
| SG-03 | C-006 | CRITICAL | CONFIRMED | Library registration не перевіряє дозволений root/allowlist типів |
| SG-04 | C-006 | CRITICAL | CONFIRMED | Є chmod із резервними 0777/0666; фактичні права невідомі |
| SG-05 | H-019 | HIGH | CONFIRMED | Є mgr; локальної авторизації дій/ресурсів у головному контролері не знайдено |
| SG-06 | H-020 | HIGH | CONFIRMED | itemType доходить до move/chmod/unlink без containment |
| SG-07 | H-020 | HIGH | CONFIRMED картки | Прямий файловий шлях через block не знайдено |
| SG-08 | H-020; C-006 | HIGH; CRITICAL для C-006 | CONFIRMED карток | Library-шляхи неоднаково використовуються у src/path і delete; containment відсутній |
| SG-09 | M-017, основний шлях | MEDIUM | CONFIRMED | w/h без верхньої межі; replay приймає params напряму |
| SG-10 | M-017, додаткова тема | MEDIUM картки | CONFIRMED картки; варіант окремо не встановлено | Вибрані API є в EVO330; питання package-name/повної сумісності лишається |
| SG-11 | M-017, додаткова тема | MEDIUM картки | CONFIRMED картки; варіант окремо не встановлено | MIME-derived view без explicit map; довільне inclusion не доведено |
| SG-12 | M-017, додаткова тема | MEDIUM картки | CONFIRMED картки; варіант окремо не встановлено | idType задається кодом; прямий HTTP-контроль назви не знайдено |
| SG-13 | M-017, додаткова тема | MEDIUM картки | CONFIRMED картки; варіант окремо не встановлено | key/lang та вкладена структура setTranslate не обмежені |
| SG-14 | M-017, додаткова тема | MEDIUM картки | CONFIRMED картки; SSRF окремо не підтверджено | Remote-read є; недовірений HTTP-caller не встановлено |
| SG-15 | C-009, multiFields | Не наведено | Не наведено | Raw href знайдено в окремому MF |
| SG-16 | M-035, ROOT | Не наведено | Не наведено | Metadata type не відповідає lowercase-схемі; невстановлюваність не доведена |
| SG-17 | I-006, ROOT | Не наведено | NOT_DETERMINED | exec_module/ModuleAccess у CORE-B є; це не gallery-action ACL |

Послідовність: SG-05 (контракт авторизації), SG-06/08 (resolver шляхів), SG-01–04 (файлова політика); SG-15 — окрема задача multiFields. Далі SG-09/11/13/14 та сумісність SG-10/16. SG-07/12/17 збережено як перевірки меж без нового висновку про експлуатацію.

**Дедуплікація:** SG-01/02 — тип/ім’я; SG-03 — право реєстрації джерела; SG-06/08 — containment/файлова ціль. Один traversal-потік не рахується двічі за C-006 та H-020. SG-13 — формат даних; доступ до чужого key — SG-05. Raw URL-output належить SG-15, не новій CRITICAL-картці ядра sGallery.

## C-006 та H-020: файли й шляхи

### SG-01 — uploadFile: MIME та кінцеве розширення

**Історія:** C-006 / CRITICAL / CONFIRMED. **Новий висновок:** клієнтський суфікс зберігається, але твердження «будь-який PHP-файл проходить MIME-перевірку» не підтверджується прочитаною залежністю.

- **Потік:** src/Controllers/sGalleryController.php:88–98 перевіряє cat як integer ≥ 1 та файл через File::types(upload_images + upload_media) і maxsize(). Типові списки: png,jpg,jpeg,mp4 та mp3,mp4. getClientOriginalName → pathinfo → slug основи + lowercase клієнтського розширення (:109–118) → move(UPLOAD + itemType + cat, filename) (:121) → chmod → saveGalleryFile (:132,435–445). Випадкового імені/перевірки колізії немає: однаковий slug/суфікс дає ту саму ціль.
- **Захисти:** File::types перевіряє MIME/вміст через framework, а не довіряє HTTP Content-Type. EVO330 core/vendor/illuminate/validation/Rules/File.php:273–280,299–319 будує mimes/mimetypes; перевірка клієнтського extensions додається лише окремим налаштуванням. EVO330 core/vendor/illuminate/validation/Concerns/ValidatesAttributes.php:1623–1637,1670–1682 використовує guessed extension та блокує php,php3,php4,php5,php7,php8,phtml,phar, якщо php явно не дозволено. Аналогічний guard є в CORE-A core/vendor/illuminate/validation/Concerns/ValidatesAttributes.php:1711,1758–1770. Є slug основи та byte-limit PHP/CMS (src/Controllers/sGalleryController.php:554–558).
- **Права/передумови/невідоме:** потрібен доступ через ефективний mgr, дозволений MIME, придатна ціль і filesystem write. Пакет не виводить суфікс із серверного MIME і не вимагає decode/re-encode перед збереженням оригіналу. Builder нижче за потоком не є upload-бар’єром. Точна dependency-версія, upload config і webserver handlers невідомі. **RCE не доведено**: одного розширення недостатньо.
- **Напрям:** server allowlist MIME ↔ канонічний суфікс ↔ обробка; політика перевірки декодування; унікальне ім’я/колізії. Шляхи — SG-06.
- **Приймання:** невідповідність типу/суфікса відхиляється до move/DB або нормалізується в дозволений канонічний суфікс; дозволені image/video/audio працюють; колізія не псує попередній файл; немає часткового запису після відмови.
- **Майбутні безпечні тести:** малі інертні PNG/JPEG/media, перейменовані файли без виконуваного вмісту, регістр/подвійні суфікси/порожній slug/колізії. Mock move/DB; перевірка відсутності побічних ефектів. Без виконання файла.

### SG-02 — uploadDownload: окрема політика документів

**Історія:** C-006 / CRITICAL / CONFIRMED. **Новий висновок:** download має обмеження MIME, але схему імені із SG-01.

- **Потік:** src/Controllers/sGalleryController.php:165–168 → mimes із upload_files (типово odp,odt,pdf,ppt,pptx,doc,docx) + max → MIME/client filename (:175–184) → move (:187) → chmod (:189–195) → DB (:198) → preview (:209).
- **Захисти/передумови:** MIME-guess/PHP-extension guard SG-01, integer cat ≥ 1, mgr. Це окремий CMS-список, незалежний від upload_images/upload_media. Зв’язку client suffix із detected type в пакеті немає. Реальні upload_files, спосіб видачі та webserver config невідомі. Preview — SG-11.
- **Напрям/приймання:** окрема політика MIME/суфіксів документів і видачі, спільне безпечне іменування. Кожен заявлений формат підтримується повністю або відхиляється до move/DB; заборонений тип не проходить завдяки імені.
- **Майбутні безпечні тести:** малі PDF/ODT/DOCX, невідповідні суфікси/MIME, byte-limit на малих штучних межах; stubs move/DB/preview. HTTP-видачу — лише після окремого дозволу в ізоляції.

### SG-03 — uploadEvoLibrary: походження та дозвіл на джерело

**Історія:** C-006 / CRITICAL / CONFIRMED. **Новий висновок:** існування й finfo не доводять належність дозволеній бібліотеці.

- **Потік:** views/partials/scripts.blade.php:281–292 передає e.target.value як file. src/Controllers/sGalleryController.php:274–276 приймає required string → trim('/') → EVO_BASE_PATH + filename → file_exists → finfo (:283–293) → зберігає відносний рядок (:296,435–445) → preview (:307). Сервер не підтверджує походження значення саме з дозволеного file browser.
- **Захисти/права:** cat integer ≥ 1, mgr, existence/MIME. Не знайдено realpath/containment, дозволених roots, is_file/is_readable, MIME/extension allowlist чи source ACL. Префікс EVO_BASE_PATH не усуває внутрішні '..'. Row може зберегтися до помилки preview, якщо БД прийме дані.
- **Подальше використання:** src/Models/sGalleryModel.php:135–140,170–175 пробує gallery-кандидат, потім EVO_BASE_PATH + file; для image формує EVO_SITE_URL + file. views/partials/image.blade.php:5 передає model.path builder. Видалення/containment описано один раз у SG-08.
- **Невідоме:** production library roots/browser ACL, symlink, права процесу, frontend-callers. Метод не доводить remote URL registration: він перевіряє локальний кандидат із EVO_BASE_PATH.
- **Напрям/приймання:** server resolver дозволених коренів/типів із source ACL; окремий статус shared/owned і право видалення. Недозволений source відхиляється до DB/preview.
- **Майбутні безпечні тести:** мале власне fixture-tree: дозволений файл, сусідній каталог, '..', backslash, symlink за межі, directory замість файла, невідомий MIME. Без читання реальних системних/секретних файлів.

### SG-04 — права каталогів і файлів

**Історія:** C-006 / CRITICAL / CONFIRMED. **Новий висновок:** широкі резервні режими передаються chmod; фактичні production-права невідомі.

- **Доказ:** src/Controllers/sGalleryController.php:123–129,189–195 читає new_folder_permissions/new_file_permissions; порожні замінює на 0777/0666; chmod після move. Derivative mkdir/chmod є в src/Builders/sGalleryBuilder.php:498–500,649.
- **Захисти/передумови:** CMS може задавати інші режими; потрібні права процесу на chmod і доступ через mgr. Результат chmod не перевіряється. Початковий move може враховувати umask (CORE-A core/vendor/symfony/http-foundation/File/UploadedFile.php:181–200); явний POSIX chmod не обмежується umask так само, як початкове створення.
- **Невідоме:** production ОС, UID/GID, ACL/mount, umask, CMS config та успіх chmod. Фактичний world-writable режим не встановлено. 0666 не додає POSIX execute-bit і саме по собі не доводить/не виключає виконання вебсерверним PHP-handler.
- **Напрям/приймання:** погодити мінімальні режими, обробляти відмови, не розширювати права shared/наявних каталогів без потреби; некоректний config дає контрольований результат.
- **Майбутні безпечні тести:** filesystem-spy для аргументів/відмов chmod; потім лише власний temp-каталог із контрольованим umask. Без chmod робочого/prod дерева.

### SG-06 — itemType → move/chmod/unlink

**Історія:** H-020 / HIGH / CONFIRMED. **Новий висновок:** потік є; канонічного containment не знайдено.

- **Потік:** request amp;itemType або itemType → trim('/', ...) (src/Controllers/sGalleryController.php:54–59) → UPLOAD + itemType + '/' + cat → move/chmod (:121–129,187–195). UPLOAD = EVO_BASE_PATH + assets/sgallery/ (src/Models/sGalleryModel.php:29). Delete: request-derived itemType → UPLOAD + itemType + gallery.parent + gallery.file → file_exists/unlink (src/Controllers/sGalleryController.php:456–462).
- **Захисти/права:** integer cat ≥ 1 на upload; filename-основа slugged; mgr. Trim прибирає лише крайові '/', не '..', внутрішні роздільники чи symlink. Delete використовує request/default itemType, а не gallery.item_type; row вибирається за global ID. Реальний вихід залежить від проміжних каталогів, ОС і прав процесу.
- **Перетин:** той самий upload sink належить C-006; containment виправляти тут без подвійного обліку. Stored library.file — SG-08.
- **Напрям:** реєстр логічних itemType; resolver із нормалізацією, canonical parent і containment із межею роздільника, symlink-policy. Delete target визначати з авторизованої моделі.
- **Приймання:** move/chmod/unlink лише в дозволеному root; prefix-sibling/symlink не обходять межі; request itemType не підміняє delete target.
- **Майбутні безпечні тести:** '..', slash/backslash, Unicode, amp;itemType, дозволені custom types; fake row з іншим item_type. Resolver/filesystem-spy і власне temp-tree, без unlink справжніх файлів.

### SG-07 — block: файловий потік не знайдено

**Історія:** H-020 / HIGH / CONFIRMED картки; окремий block-to-file варіант не встановлено. **Новий висновок:** block не є сегментом розглянутих файлових цілей.

- **Доказ:** amp;block/block → trim (src/Controllers/sGalleryController.php:43–48) → whereBlock (:71,345), DB key (:440); DOM ID має slug/camel (:509–514). У move/chmod (:121–129,187–195) і unlink (:459–461) block відсутній.
- **Права/межі:** mgr; incoming block без allowlist і впливає на data scope — SG-05. Валідація налаштованих block у src/Controllers/GallerySettingsController.php:70–71 не валідує довільний request головного контролера. Зовнішні інтеграції не перевірено.
- **Напрям/приймання:** визначити дозволені блоки; зберегти відсутність block у filesystem targets. Не створювати traversal-fix без нового доказу.
- **Майбутні безпечні тести:** request/query і resolver-spies: block змінює лише дозволену групу даних, не файлову ціль; міжблоковий ACL — SG-05.

### SG-08 — stored library.file: src/path і delete

**Історія:** H-020 / HIGH / CONFIRMED; перетин C-006 / CRITICAL / CONFIRMED. **Новий висновок:** правила розходяться; автоматичне видалення оригіналу кожного library-файла не доведено.

- **Потік:** SG-03 зберігає file без containment → src/Models/sGalleryModel.php:135–140,170–175 пробує gallery/EVO_BASE_PATH candidates. ScopeLang будує SQL-src для assets/% або gallery URL (:104–108); це URL, не ACL. Delete (src/Controllers/sGalleryController.php:456–463) видаляє переклади, перевіряє лише UPLOAD + requestItemType + gallery.parent + gallery.file, потім видаляє row.
- **Висновок:** звичайний library.file assets/... не отримує в delete той самий EVO_BASE_PATH-кандидат; оригінал може залишитися після видалення row/перекладів. Traversal у stored file/requestItemType не відсікається й може змінити ціль. File_exists не є ownership/containment. Досяжність конкретної цілі залежить від проміжних шляхів і прав процесу.
- **Права/невідоме:** mgr та item ID; resource ACL — SG-05. Невідомі DB values, symlink і спільні користувачі library-файла.
- **Напрям/приймання:** єдиний resolver читання/видалення з класами owned/shared/remote; від’єднання shared не видаляє оригінал; owned delete видаляє лише свій файл; авторизація до видалення перекладів; контрольований стан при filesystem failure.
- **Майбутні безпечні тести:** дві fake галереї на один інертний fixture, owned/shared, traversal/symlink, відмова unlink; filesystem/DB-spies або temp-tree. Без реальних library-видалень.

## H-019: manager, дія та ресурс

### SG-05 — авторизація кожного entry point

**Історія:** H-019 / HIGH / CONFIRMED. **Новий висновок:** локальний дефіцит action/resource перевірок є; анонімний доступ і відсутність зовнішнього захисту не доведені.

src/Http/routes.php:6 використовує mgr і prefix sgallery/. src/sGalleryServiceProvider.php:22–23 реєструє mgr із config('app.middleware.mgr', []): склад залежить від сайта. CORE-A core/config/app.php:122–129 містить session, CSRF і Manager. CORE-A core/src/Middleware/Manager.php:15–25 перевіряє manager auth/access з винятками login/logout action. CORE-A core/src/ManagerTheme.php:527–555,616–619 показує залежність від action/access_permissions. Це не gallery-row policy. CORE-A core/src/Middleware/VerifyCsrfToken.php:85–119 перевіряє CSRF для manager-сесії; sGallery UI передає token (views/partials/scripts.blade.php:74–79). Runtime pipeline/винятки конкретного сайта не відтворювалися.

| Дія | Маршрут у src/Http/routes.php | Дані → операція, sGalleryController.php | Локальне обмеження / прогалина |
| --- | --- | --- | --- |
| Читання галереї | GET /sgallery/, :10 | request->{$idType} → query/view, :67–75 | parent/block/item_type filters; немає права читати parent |
| Upload media | POST upload-file, :11 | cat/file → move/DB, :84–151 | MIME/max/integer cat; немає cat existence/resource ACL/action permission |
| Upload download | POST upload-download, :12 | cat/file → move/DB, :161–217 | Аналогічно; окремий MIME-список |
| Library registration | POST upload-evo-library, :13 | cat/file → finfo/DB, :270–314 | existence; немає ACL parent/source |
| YouTube | POST upload, :14 | cat/youtubeLink → DB, :226–261 | integer cat, active_url, regex ID; немає action/resource permission |
| Reorder | POST resort, :15 | cat/item[] → scoped rows → position update, :323–353 | IDs → int; parent/block/item_type scope є; право змінювати scope не перевіряється |
| Читання перекладів | GET translate, :17 | item → where key → view, :362–382 | integer item; немає authorized parent/block/type scope |
| Редагування полів/перекладів | POST translate, :18 | list[first key][lang] → updateOrCreate, :391–421 | list=array; немає action/resource ACL |
| Видалення | POST delete, :16 | item → global find → fields/unlink/row, :454–471 | int cast; немає authorized scope/delete permission |
| Налаштування | POST settings, :8 | src/Controllers/GallerySettingsController.php:41–44 | hasPermission(settings) + власний session token |
| Кеш | POST settings-cache, :7 | src/Controllers/GallerySettingsController.php:11–17 | settings permission + token + active-directory match |
| Watermark upload | POST settings-watermark, :9 | src/Controllers/GallerySettingsController.php:24–35 | settings permission + token, MIME/content/size, fixed dir/random name |

Окремого HTTP endpoint довільного редагування gallery row/file не знайдено: редагування текстових полів — setTranslate. SQL scope reorder/int cast не є авторизацією. Умовний показ вкладки за template (plugins/sGalleryPlugin.php:6–18) не замінює серверну policy.

- **Передумови/невідоме:** actor проходить ефективний mgr, для write має CSRF, якщо його вимагає CMS. Ролі, resource ACL, додаткові middleware/global scopes і direct PHP-callers невідомі. Settings permission іншого контролера не захищає gallery endpoints автоматично.
- **Напрям:** policy(actor, action, itemType, parent, block, gallery/library source) перед кожним читанням/побічною дією; контракт custom product/module ACL; global item/key шукати через authorized scope.
- **Приймання:** дозволена роль працює зі своїм ресурсом; роль без дії отримує відмову; upload permission не дає delete; чужі parent/block/type/key не читаються й не змінюються; відмова не змінює DB/order/filesystem. Settings-захист зберігається.
- **Майбутні безпечні тести:** кожний рядок матриці для guest, manager без дії, дозволеної ролі, іншого ресурсу та admin. Fake rows/in-memory DB, middleware/policy/filesystem-spies; CSRF окремо від ACL. HTTP integration — лише після окремого дозволу в ізоляції.

## M-017: незалежні теми

### SG-09 — межі w/h і replay оптимізації

**Історія:** M-017 / MEDIUM / CONFIRMED основного w/h-шляху. **Новий висновок:** верхньої межі немає; віддалений DoS і витрати ресурсів не виміряні.

- **Потік:** fluent resize/fit/crop/focalCrop/manualCrop приймають PHP-int, записують max(1, width/height) (src/Builders/sGalleryBuilder.php:261–265,277–292,305–323,339–346,362–369) → params → Image fit/crop/width/height (:562–571,693–702). optimizationDescriptor (:767–784) → src/Services/ImageOptimizationQueue.php:84–94 → task options → src/Workers/ImageOptimizationWorker.php:40–45 → processOptimization (:822–843), який призначає job.params напряму без fluent нижніх меж і без верхніх.
- **Захисти:** manager-preview використовує сталі 240×120 (views/partials/image.blade.php:5; src/sGallery.php:18–19,233–245). HTTP w/h endpoint у routes не знайдено. Replay перевіряє local source, окремі traversal/scheme patterns/source_version (:824–831), quality (:840). Queue має lock/dedup/cooldown (src/Services/ImageOptimizationQueue.php:69–94).
- **Межі:** 5-секундний бюджет (src/sGallery.php:20,254–256) кооперативний; перевірка є вже після transforms (builder :586,801–810,1096), не перериває native operation. Background bypass; CLI worker ставить set_time_limit(0) (:42–43). Upload byte-limit не є pixel-limit.
- **Права/невідоме:** потрібен caller із великими dimensions або вплив на job. Недовірений HTTP-caller w/h і права створення/зміни sTask не встановлені. Driver limits, RAM/concurrency/source dimensions невідомі.
- **Напрям/приймання:** одна валідація width/height/площі та source dimensions до decode/enqueue/replay; worker resource policy. Надмірні/неправильні params відхиляються до Image::load/save та cache/queue markers.
- **Майбутні безпечні тести:** max/max+1, zero/negative, type mismatch, overflow добутку зі штучними малими межами (наприклад 8/9 px); image/queue mocks, fake clock. Жодних великих canvas/exhaustion tests.

### SG-10 — мінімальна CMS та використані API

**Історія:** M-017 / MEDIUM / CONFIRMED картки; окреме підтвердження несумісності відсутнє у витягу. **Новий висновок:** вибрані API доступні в EVO330; повна сумісність не доведена.

- **Доказ:** composer.json:14–16 вимагає PHP ^8.3, evolution-cms/evolution ^3.3, spatie/image 3.*; README.md:51–53 і docs/pages/getting-started.md:9–10 заявляють CMS 3.3+. EVO330 core/composer.json:48,58–59 має Illuminate 11; його core/vendor/illuminate/validation/Rules/File.php:134 має File::types, core/vendor/illuminate/database/Eloquent/Builder.php:647 — updateOrCreate. EVO330 core/src/ServiceProvider.php:46 має loadPluginsFrom; core/functions/laravel.php:29,69 — config_path(custom) та public_path. Ці API не доводять потребу новішої CMS.
- **Metadata-вузол:** EVO330 composer.json:2 називає evolutioncms/evolution; core/composer.json:102–105 має conflict/replace для цього імені. SGallery вимагає evolution-cms/evolution. У CORE-A core/composer.json:2 вже нове ім’я. Потрібна звірка dependency resolution мінімального релізу; гарантований install failure не встановлено, додаткові repositories/replace не відтворювалися.
- **Права/межі:** оператор інсталяції/boot. У sGallery немає composer.lock/vendor; resolved набір невідомий. Optional sTask має guards (src/Services/ImageOptimizationQueue.php:18–29; src/Workers/ImageOptimizationWorker.php:8–10), а не доведену обов’язкову залежність мінімальної CMS.
- **Напрям/приймання:** зафіксувати release/dependency matrix; підтвердити API/constraints або окремо змінити requirements/docs. Не оголошувати 3.3 несумісною лише через назву File::types.
- **Майбутні безпечні тести:** офлайн API/constraints на manifests; після окремого дозволу — одноразова CMS-копія для boot/install smoke. Без scripts/міграцій робочого сайта.

### SG-11 — view через filetype

**Історія:** M-017 / MEDIUM / CONFIRMED картки; підсценарій окремо не встановлено. **Новий висновок:** MIME впливає на view; довільне template/file inclusion не доведено.

- **Потік:** detected MIME major, для application — subtype (src/Controllers/sGalleryController.php:111–114,177–180,286–293) → stored type → view('partials.' + filetype) (:143,209,307) → GallerySettings::view (:537–539) → namespace sGallery:: (src/Services/GallerySettings.php:91–93). DB type також використовується в includeIf (views/sectionDownloads.blade.php:11).
- **Захисти/межі:** filetype не є direct request value; upload має MIME-список, namespace фіксований; library допускає ширший finfo-набір. Є partials image/video/pdf/youtube, але не всі office subtypes. Початкова DB type має довжину 10 (database/migrations/2022_05_15_185152_create_s_gallery_table.php:21), тому залежно від DB/режиму помилка може виникнути до preview.
- **Права/невідоме:** mgr для upload/register; MIME/custom views config та фактична DB schema невідомі. Шлях до LFI/RCE не встановлено.
- **Напрям/приймання:** explicit MIME → media type → known view map; безпечний download fallback або відмова до move/DB; усі дозволені MIME мають визначений результат без partial persistence.
- **Майбутні безпечні тести:** table-driven MIME cases зі spy view/DB та малими документами; unknown subtype не стає довільним view name.

### SG-12 — динамічний request->{$idType}

**Історія:** M-017 / MEDIUM / CONFIRMED картки; підсценарій окремо не встановлено. **Новий висновок:** конструкція є, довільний HTTP-вибір назви у відомих callers не знайдено.

- **Потік:** sGalleryBuilder::idType(string) (:121–124) → getView (:896–899) → constructor зберігає idType без request.idType (src/Controllers/sGalleryController.php:29,50–51) → request-властивість (:69) → bound whereParent query. Route controller має default id; plugin передає id (plugins/sGalleryPlugin.php:13,16); integration example — i (README.md:137).
- **Захисти/права:** назву задає PHP-код; HTTP idType не використовується як selector у цьому шляху. ORM query не доводить SQL injection. Parent у index не валідовано як integer; resource ACL — SG-05.
- **Невідоме:** зовнішні custom callers, які можуть передати недовірену назву.
- **Напрям/приймання:** дозволені bindings/реєстр custom idType, валідація parent. Не створювати «виправлення довільного HTTP-idType» без доказу caller.
- **Майбутні безпечні тести:** id/i/custom binding, array/absent/non-numeric value; request.idType не змінює selector. Request/query mocks без зовнішньої DB.

### SG-13 — setTranslate: key/lang та вкладена структура

**Історія:** M-017 / MEDIUM / CONFIRMED картки; підсценарій окремо не встановлено. **Новий висновок:** валідується лише зовнішній list=array.

- **Потік:** src/Controllers/sGalleryController.php:395–397 → first key (:403–404) → lang/items (:406) → updateOrCreate(key,lang,explicit fields) (:407–416). src/Models/sGalleryField.php:18 обмежує fillable цими полями. Немає gallery existence/numeric key/allowed lang/вкладених type-length перевірок; береться лише перший верхній key.
- **Захисти/межі:** explicit fields/ORM binding; DB задає integer key, lang(4), alt/title(100), description/link(1024) (початкова міграція :26–34). DB constraints не замінюють request schema і залежать від driver. UI бере мови із s_lang_config (src/sGallery.php:160–166), incoming lang сервер із ним не звіряє.
- **Права/невідоме:** mgr/CSRF за pipeline; чужий key — SG-05. URL-схеми link не обмежені; raw sink — SG-15. Фактичні DB constraints/config невідомі.
- **Напрям/приймання:** schema list/key/lang/fields, single/multi-key семантика, довжини/URL-policy; invalid data відхиляються до update, правильний український текст зберігається.
- **Майбутні безпечні тести:** малий in-memory DB/repository-spy, empty/multi-key list, scalar замість array, unknown lang, absent gallery, межі довжин. Без виконуваних SQL/HTML payload.

### SG-14 — file_get_contents($this->file) і межі SSRF-висновку

**Історія:** M-017 / MEDIUM / CONFIRMED картки; основний w/h-доказ не підтверджував SSRF. **Новий висновок:** HTTP(S)-read є, недовірене походження URL не встановлено.

- **Потік:** src/sGallery.php:55–63 → builder.file (:883–887, trim/str_replace base URL/path) → getFile/__toString (:435–439,908–915). HasLink допускає початок lowercase http:// або https:// (src/sGallery.php:146–152). Cache miss → file_get_contents цілого ресурсу (builder :508–522); AVIF→WebP fallback може повторити read (:679–685).
- **Callers:** views/partials/image.blade.php:5 та pdf.blade.php:4 передають gallery.path; модель повертає local/HTTP(S)-fallback (src/Models/sGalleryModel.php:164–178). MF views/hero_slider_sgallery.blade.php:8 передає item.path. Docs показують application-selected product.cover. Endpoint із прямим HTTP input → sGallery::file у пакеті не знайдено. UploadEvoLibrary сам не встановлює remote URL як remote source — SG-03.
- **Захисти/межі:** hasLink обмежує схеми; remote виключено з descriptor (:769–770), replay відхиляє scheme (:825–827). Read не має explicit host/IP allowlist, private/loopback policy, redirect/DNS-контролю, власного timeout/byte cap; content-check після читання (:535–536). Це потенційний SSRF/resource-risk **за наявності недовіреного caller**, не доведений internal request.
- **Права/невідоме:** контроль DB file/custom cover, allow_url_fopen, network rules, redirects/default stream context/timeout/DNS. Відомі manager-callers потребують доступу; зовнішні frontend-callers можуть мати іншу модель.
- **Напрям/приймання:** inventory callers; remote-fetch service із host/IP/redirect/rebinding policy, byte/time limits або remote off by default. Заборонений URL відхиляється до transport; обидві гілки використовують одну policy.
- **Майбутні безпечні тести:** URL-policy на рядках public/private/IPv6/схемах; fake transport для redirect/timeout/byte cap. Без реальних localhost/metadata/external запитів.

## Пов’язані картки інших компонентів

### SG-15 — C-009: raw href у multiFields

**Історія:** C-009 / multiFields; severity/status у витягу не наведені. **Новий висновок:** raw href лишився у локальному MF; використання template на сайті не встановлено.

- **Потік:** setTranslate зберігає link (src/Controllers/sGalleryController.php:414) → fields/lang join (src/Models/sGalleryModel.php:109–119) → MF views/hero_slider_sgallery.blade.php:2 отримує image collections → :14–15 виводить item.link raw усередині href через Blade {!! !!}. Trim перевіряє лише непорожність; attribute escaping/URL-scheme allowlist відсутні. Title/description/link_text поруч escaped через {{ }}.
- **Права/передумови:** можливість записати link (SG-05), активний template, відвідувач; schema-based поведінка може потребувати переходу. CSP/site mutators/санітизація та використання template невідомі. Ризик stored attribute injection/небезпечної схеми статично є; браузерне виконання не перевірялося.
- **Власник:** sink — multiFields; storage URL-policy погодити із sGallery. **Усю C-009 та її можливу історичну severity не перенесено в ядро sGallery.**
- **Напрям/приймання:** escaped href + окрема URL-policy; HTML-escaping сам не блокує небезпечну схему. Лапки/амперсанди лишаються даними в одному атрибуті; погоджені HTTPS/relative URLs працюють, заборонені схеми відсікаються.
- **Майбутні безпечні тести:** offline render/DOM-аналіз інертних рядків/керівних символів/схем без кліків, скриптів або браузерного виконання.

### SG-16 — M-035 ROOT: type та інсталяційні вимоги

**Історія:** M-035 / ROOT; severity/status не наведені; історична картка не довела невстановлюваність. **Новий висновок:** metadata/schema-проблема є; install failure не доведено.

- **Доказ:** composer.json:4 містить EvolutionCMS-Plugin; :18–33 — PSR-4/extra.laravel provider/alias. README.md:59–81 вимагає core/ → package:installrequire → vendor:publish → migrate. CORE-A core/vendor/composer/composer/res/composer-schema.json:19–22 задає custom type pattern ^[a-z0-9-]+$: поточний регістр йому не відповідає.
- **Контрдоказ гарантованого збою:** CORE-A core/vendor/composer/composer/src/Composer/Package/Loader/ArrayLoader.php:156 нормалізує type через strtolower; core/vendor/composer/composer/src/Composer/Installer/LibraryInstaller.php:68–70 підтримує custom type при installer type=null; core/vendor/composer/composer/src/Composer/Factory.php:589 реєструє такий default installer. CORE-A core/src/Console/Packages/InstallPackageRequireCommand.php:46–65,76–115 додає custom dependency/викликає Composer без exact-type gate sGallery у цьому шляху.
- **Права/межі:** оператор CLI/деплою. Composer.json:36–39 має post-autoload-dump із php artisan migrate; це не доказ автоматичного виконання dependency scripts у consumer. Discovery/publish/migrate не запускалися. Resolution/install конкретного сайта невідомі; мінімальне package-name — SG-10.
- **Напрям/приймання:** lowercase type та CMS discovery/publish/migrate контракт; manifest відповідає схемі, provider/alias знаходяться, міграції — погоджений етап.
- **Майбутні безпечні тести:** offline schema/loader без scripts/plugins/network; install smoke лише після окремого дозволу в одноразовому середовищі.
- Історичні шаблонні згадки **command injection не прийняті як факт**: input-to-shell шлях у цьому сценарії не встановлено.

### SG-17 — I-006 ROOT: module ACL та дії галереї

**Історія:** I-006 / ROOT / **NOT_DETERMINED**; severity не наведена. **Новий висновок:** module-захист у ядрі є; застосовність до всіх sGallery endpoints не доведена.

- **Доказ CORE-B:** manager/processors/execute_module.processor.php:9–10 перевіряє exec_module; numeric module має group/resource обмеження (:27–38), file module — registry (:51–57) і ModuleAccess::canRunFileModule (:61–62) до читання/виконання. core/src/Support/ModuleAccess.php:39–50,83–105 враховує admin/declared roles; empty roles — unrestricted на цьому рівні, не відсутність manager/exec перевірок.
- **Інший entry point:** CORE-B core/src/Middleware/RoutingModuleAccess.php:21–42 перевіряє exec_module/file-module ACL для routing modules. SGallery src/Http/routes.php:6–19 прямо називає лише mgr, не цей module-specific guard.
- **Права/межі:** actor проходить manager/module ACL; CORE-A/CORE-B — різні зрізи, їх не узагальнено на всі CMS. SGallery — plugin із власними маршрутами; вкладка всередині custom module не доводить однаковий ACL прямого POST. Твердження «ядро нічого не перевіряє» суперечить коду.
- **Напрям/приймання:** визначити передачу module policy для gallery resources; перевіряти всі entry points. Manager, exec_module, module roles та gallery action/resource — окремі перевірки. Новий ROOT-дефект тут не оголошено; локальні action ACL — SG-05.
- **Майбутні безпечні тести:** unit policy-matrix на fake roles/module registry, статичні маршрутні контракти; без evalModule/запуску модулів.

## Спільні критерії майбутньої роботи

1. Кожна зміна посилається на SG-запис/історичний ID; новий результат не переписує історичні severity/status.
2. Відмова передує move/chmod/unlink/DB update/remote fetch/image decode. Тести перевіряють відсутність побічних ефектів.
3. Позитивні сценарії охоплюють resource/custom itemType, кілька блоків, українські переклади, owned upload/shared library.
4. Fixtures малі й інертні; filesystem — temp-tree/spies; network/image-driver/worker — mocks до окремо погодженої integration перевірки.
5. Не читати секрети, не виконувати uploads, не звертатися до internal/metadata адрес, не створювати великі canvas і не виснажувати RAM/CPU/диск.
6. Для runtime-оцінки потрібні CMS/dependency version, effective middleware/ACL, webserver handlers assets, permissions/umask/ACL, library roots, network/image-processing policy.
7. Існуючі тести переглянуто лише як текст. Їх наявність не означає проходження/покриття сценаріїв. У цій задачі виправлення й тести не реалізовані та не запущені.

Інвентар зберігає всі запитані картки, включно з варіантами без нового встановленого потоку. Експлуатація, анонімний доступ, RCE, SSRF, production-права і працездатність інсталяції цим документом не підтверджуються.
