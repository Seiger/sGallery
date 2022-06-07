# sGallery for Evolution CMS 3
![sGallery](https://user-images.githubusercontent.com/12029039/169609394-08ea36d6-2393-4261-aff2-348f73a6103c.png)
[![Latest Stable Version](https://img.shields.io/packagist/v/seiger/sgallery?label=version)](https://packagist.org/packages/seiger/sgallery)
[![CMS Evolution](https://img.shields.io/badge/CMS-Evolution-brightgreen.svg)](https://github.com/evolution-cms/evolution)
![PHP version](https://img.shields.io/packagist/php-v/seiger/sgallery)
[![License](https://img.shields.io/packagist/l/seiger/sgallery)](https://packagist.org/packages/seiger/sgallery)
[![Issues](https://img.shields.io/github/issues/Seiger/sgallery)](https://github.com/Seiger/sgallery/issues)
[![Stars](https://img.shields.io/packagist/stars/Seiger/sgallery)](https://packagist.org/packages/seiger/sgallery)
[![Total Downloads](https://img.shields.io/packagist/dt/seiger/sgallery)](https://packagist.org/packages/seiger/sgallery)

**sGallery** Plugin for attaching Images and Video clips (YouTube) to a resource in the Evolution CMS admin panel.

## Features

- [x] Upload Images.
- [x] Upload Videos.
- [x] Include Youtube.
- [x] Sort positions.
- [x] Text fields for file.
- [x] Resize and WEBP convert image.
- [x] Integration the gallery to custom modules.

## Install by artisan package installer

Run in you /core/ folder:

```console
php artisan package:installrequire seiger/sgallery "*"
```

Create config file in **core/custom/config/cms/settings** with 
name **sgallery.php** the file should return a 
comma-separated list of templates.

```console
php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"
```

Run make DB structure with command:

```console
php artisan migrate
```

## Configure

Templates for displaying gallery tabs are configured in the 

```console
core/custom/config/cms/settings/sGallery.php
```

file, where the array contains template IDs for connecting the gallery.

## Usage in blade

Sow all files:
```php
@foreach(sGallery::all() as $item)
    @if(sGallery::hasImage($item->type))
        <a class="swiper-slide" @if(trim($item->link))href="{{$item->link}}"@endif>
            <div class="container">
                <img loading="lazy" class="intro__img" src="{{$item->file_src}}" alt="{{$item->alt}}" width="1440" height="456">
                <div class="intro__inner">
                    <div class="h1__title">{{$item->title}}</div>
                    <p class="intro__text">{{$item->description}}</p>
                    @if(trim($item->link_text))<div class="btn background__mod">{{$item->link_text}}</div>@endif
                </div>
            </div>
        </a>
    @endif
@endforeach
```
or
```php
@foreach(sGallery::all('product', $product->id) as $item)
    <div class="swiper-slide">
        <a class="js-trigger-fancybox" href="{{$item->file_src}}" data-fancybox="product-gallery">
            <img loading="lazy" src="{{$item->file_src}}" width="440" height="440" />
        </a>
    </div>
@endforeach
```

## Integration into the products module

Just paste this code in your View backend
```php
{!! sGallery::initialise('section', 'product', 'i') !!}
```