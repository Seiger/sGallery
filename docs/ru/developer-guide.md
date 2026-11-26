---
id: developer-guide
title: Руководство разработчика
sidebar_position: 6
---

# Руководство разработчика

Метод `collections()` возвращает записи галереи. Выберите тип, ID родителя, блок и язык. Для собственного типа зарегистрируйте [проверку доступа](./configuration.md). Примеры вывода приведены в [руководстве Blade](./use-in-blade.md).

```php
$url = sGallery::file($item->src)
    ->fit('max', 800, 600)
    ->format('webp')
    ->getFile();
```

`getFile()` возвращает URL результата. Если преобразование долгое или требует много памяти, оно может выполняться [через sTask](./background-image-optimization.md).
