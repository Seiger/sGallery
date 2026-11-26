---
id: width-and-height
title: Ширина та висота
slug: /manipulations/width-and-height/
sidebar_position: 5
---

# Ширина та висота

Методи `resize()`, `fit()` і `crop()` приймають розміри в пікселях. Якщо висоту опущено, рамка має однакові ширину й висоту. Фактичний розмір похідного зображення залежить від режиму [підгонки](./fit.md) і пропорцій джерела.

```php
$url = sGallery::file($item->src)->fit('max', 500, 300)->getFile();
```
