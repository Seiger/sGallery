---
layout: page
title: Use in Blade
description: Use sGallery code in Blade layouts
permalink: /use-in-blade/
---

## Sow all files with Image filter

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

## Sow all files with YouTube filter

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

## Sow all product files

```php
@foreach(sGallery::all('product', $product->id) as $item)
    <div class="swiper-slide">
        <a class="js-trigger-fancybox" href="{% raw %}{{$item->src}}{% endraw %}" data-fancybox="product-gallery">
            <img loading="lazy" src="{% raw %}{{$item->src}}{% endraw %}" width="440" height="440" />
        </a>
    </div>
@endforeach
```

## Sow first product file

You can use it for an example cover.

```php
@php($item = sGallery::first('product', $product->id))
<img loading="lazy" src="{% raw %}{{$item->src}}{% endraw %}" alt="{% raw %}{{$item->alt}}{% endraw %}" width="440" height="440" />
```

## Sow product cover

Call the code from the previous example with a full list of arguments.

```php
/**
* @param string $resourceType (Optional) The type of resource to retrieve. Default is 'resource'.
* @param int|null $documentId (Optional) The ID of the document to retrieve. Default is null.
* @param string|null $lang (Optional) The language of the resource to retrieve. Default is null.
* @param string|null $block (Optional) The block name if you need block filter.
* @return object The first object from the sGalleryModel.
*/
@php($item = sGallery::first('product', $product->id, 'en', 'photo'))
<img loading="lazy" src="{% raw %}{{$item->src}}{% endraw %}" alt="{% raw %}{{$item->alt}}{% endraw %}" width="440" height="440" />
```

## Gallery items if blocks are used

In the event that you need more than one gallery per page, you will [use blocks]({{site.baseurl}}/configuration/#more-than-one-tab).

In this case, you must use the `block()` method to output units from the selected gallery block:

```php
@foreach(sGallery::block('cinema') as $item)
    @if(sGallery::hasYoutube($item->type))
        <lite-youtube videoid="{% raw %}{{$item->file}}{% endraw %}"></lite-youtube>
    @endif
@endforeach
```

An example of forming an image slider for a product with a full set of call parameters.

```php
/**
* @param string $blockName The name of the block (default: '1').
* @param string $resourceType The type of resource (default: 'resource').
* @param int|null $documentId The ID of the document to block gallery (default: current document ID).
* @param string|null $lang The language to use (default: value from Evo configuration).
* @return object The galleries matching the given resource type, block name, document ID, and language.
 */
@foreach(sGallery::block('photo', 'product', $product->id, 'en') as $item)
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
