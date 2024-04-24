---
layout: page
title: Configuration
description: Configuration sGallery tabs
permalink: /configuration/
---

## Config file

The configuration file is located here:

```console
../core/custom/config/seiger/settings/sGallery.php
```

## Simple configuration

The easiest way to add a gallery to a resource is to set up an array of numbers.

```php
<?php return [1, 3, 5];
```

This array contains a list of resource template IDs.
If you use this simple setting, an additional tab with a gallery will be added to the resources with the template listed.

## More than one tab

When a resource template requires more than one gallery, the configuration file will look like this

```php
<?php return [
    1, // ID Template
    3, // ID Template
    [
        5 => [ // ID Template
            'block 1', // New Tab name
            'block 2', // New Tab name
        ]
    ],
];
```

To visually separate tabs with galleries, a block name is added to the tab header. In this case, it is block 1 and block 2.
