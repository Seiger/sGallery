---
layout: page
title: Getting started
description: Getting started with sGallery
permalink: /getting-started/
---

## Minimum requirements

- Evolution CMS 3.2.0
- PHP 8.1.0
- Composer 2.2.0
- PostgreSQL 10.23.0
- MySQL 8.0.3
- MariaDB 10.5.2
- SQLite 3.25.0

## Install by artisan package

Go to You /core/ folder

```console
cd core
```

Run php artisan commands

```console
php artisan package:installrequire seiger/sgallery "*"
```

```console
php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"
```

```console
php artisan migrate
```

## Configuration

Templates for displaying gallery tabs are configured in the

```console
../core/custom/config/seiger/settings/sGallery.php
```

file, where the array contains template IDs for connecting the gallery.

```php
<?php return [1, 3, 5];
```

More examples in **Configuration** page

[Configuration]({{site.baseurl}}/configuration/){: .btn .btn-sky}

## Usage in blade

Sow all files with Image filter:

```php
@foreach(sGallery::all() as $item)
    @if(sGallery::hasImage($item->type))
        <a class="swiper-slide" @if(trim($item->link))href="{% raw %}{{$item->link}}{% endraw %}"@endif>
            <div class="container">
                <img loading="lazy" class="intro__img" src="{% raw %}{{$item->src}}{% endraw %}" alt="{% raw %}{{$item->alt}}{% endraw %}" width="1440" height="456">
                <div class="intro__inner">
                    <div class="h1__title">{% raw %}{{$item->title}}{% endraw %}</div>
                    <p class="intro__text">{% raw %}{{$item->description}}{% endraw %}</p>
                    @if(trim($item->link_text))<div class="btn background__mod">{% raw %}{{$item->link_text}}{% endraw %}</div>@endif
                </div>
            </div>
        </a>
    @endif
@endforeach
```

or YouTube filter

```php
@foreach(sGallery::all() as $item)
    @if(sGallery::hasYoutube($item->type))
        <div class="item">
            <div class="video">
                <iframe width="560" height="315" src="https://www.youtube.com/embed/{% raw %}{{$item->file}}{% endraw %}" title="YouTube video player" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            </div>
            <p>{% raw %}{{$item->title}}{% endraw %}</p>
        </div>
    @endif
@endforeach
```

or

```php
@foreach(sGallery::all('product', $product->id) as $item)
    <div class="swiper-slide">
        <a class="js-trigger-fancybox" href="{% raw %}{{$item->src}}{% endraw %}" data-fancybox="product-gallery">
            <img loading="lazy" src="{% raw %}{{$item->src}}{% endraw %}" width="440" height="440" />
        </a>
    </div>
@endforeach
```

More examples in **Use in Blade** page

[Use in Blade]({{site.baseurl}}/use-in-blade/){: .btn .btn-sky}

## Integration into the products module

Just paste this code in your View backend
```php
{!!sGallery::initialise('section', 'product', 'i')!!}
```

Or if you want to use additional blocks
```php
{!!sGallery::initialise('section', 'ship', 'i', 'photo')!!}
```

## Extra

If you write your own code that can integrate with the sGallery plugin, you can check the presence of this plugin in the system through a configuration variable.

```php
if (evo()->getConfig('check_sGallery', false)) {
    // You code
}
```

If the plugin is installed, the result of ```evo()->getConfig('check_sGallery', false)``` will always be ```true```. Otherwise, you will get an ```false```.
