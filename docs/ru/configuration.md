---
id: configuration
title: Настройки
slug: /configuration/
sidebar_position: 3
---

# Настройки

Настройки проекта хранятся в `core/custom/config/seiger/settings/sGallery.php`. В примере шаблон 5 получает два именованных блока, а шаблоны 1 и 3 — обычную галерею.

```php
<?php return [
    5 => ['slider', 'top-sales'],
    1,
    3,
];
```

Чтение требует доступа к ресурсу и права `view_document` или `edit_document`. Для изменений нужны `edit_document` и `save_document`, для удаления дополнительно `delete_document`. Условия доступа к собственным типам задаются через `itemPolicies`.

Папка результата по умолчанию — `assets/cache/`. Параметр `imageCacheDir` задаёт другой путь. Очистка через менеджер удаляет индексированные производные файлы и сохраняет оригиналы.
