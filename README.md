# sGallery for Evolution CMS 3
![sLang](https://user-images.githubusercontent.com/12029039/167660172-9596574a-47ae-4304-a389-814bfa4c9e87.png)
[![Latest Stable Version](https://img.shields.io/packagist/v/seiger/slang?label=version)](https://packagist.org/packages/seiger/slang)
[![CMS Evolution](https://img.shields.io/badge/CMS-Evolution-brightgreen.svg)](https://github.com/evolution-cms/evolution)
![PHP version](https://img.shields.io/packagist/php-v/seiger/slang)
[![License](https://img.shields.io/packagist/l/seiger/slang)](https://packagist.org/packages/seiger/slang)
[![Issues](https://img.shields.io/github/issues/Seiger/slang)](https://github.com/Seiger/slang/issues)
[![Stars](https://img.shields.io/packagist/stars/Seiger/slang)](https://packagist.org/packages/seiger/slang)
[![Total Downloads](https://img.shields.io/packagist/dt/seiger/slang)](https://packagist.org/packages/seiger/slang)

**sLang** Seiger Lang multi language Management Module for Evolution CMS admin panel.

The work of the module is based on the use of the standard Laravel functionality for multilingualism.

## Features

- [x] Automatic translation of phrases through Google.
- [x] Automatic search for translations in templates.
- [x] Unlimited translation languages.

## Install by artisan package installer

Go to You /core/ folder:

```console
cd core
```

Run php artisan command

```console
php artisan package:installrequire seiger/slang "*"
```

```console
php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"
```

Run make DB structure with command:

```console
php artisan migrate
```

## Usage in blade
Current language:
```php
[(lang)]
```

Translation of phrases:
```php
@lang('phrase')
```

Default language:
```php
[(s_lang_default)]
```

List of frontend languages by comma:
```php
[(s_lang_front)]
```

Localized versions of your page for Google hreflang
```php
@php($sLang = new sLang())
{!!$sLang->hrefLang()!!}
```

[See documentation here](https://seiger.github.io/seigerlang/)