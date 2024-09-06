---
layout: page
title: Use in Blade
description: Use sGallery functionality in Blade layouts
permalink: /use-in-blade/
---

# Using sGallery in Blade Templates

This documentation provides a comprehensive guide on how to use `sGallery` within your Blade templates for
retrieving and displaying images, videos, and other media from the sGallery module. We'll cover everything
from displaying filtered collections to managing multiple galleries on a single page.

---

## Display All Files with Image Filter

This example demonstrates how to display all files in a collection that are identified as images.

```php
@foreach(sGallery::collections()->get() as $item)
    @if(sGallery::hasImage($item->type))
        <a class="swiper-slide" @if(trim($item->link)) href="{% raw %}{{$item->link}}{% endraw %}" @endif>
            <div class="container">
                <img loading="lazy" class="intro__img" src="{% raw %}{{$item->src}}{% endraw %}" alt="{% raw %}{{$item->alt}}{% endraw %}" width="1440" height="456">
                <div class="intro__inner">
                    <div class="h1__title">{% raw %}{{$item->title}}{% endraw %}</div>
                    <p class="intro__text">{% raw %}{{$item->description}}{% endraw %}</p>
                    @if(trim($item->link_text)) 
                        <div class="btn background__mod">{% raw %}{{$item->link_text}}{% endraw %}</div> 
                    @endif
                </div>
            </div>
        </a>
    @endif
@endforeach
```

### Explanation:

- `sGallery::collections()->get()` retrieves all files in the gallery.
- `sGallery::hasImage($item->type)` checks if the file type is an image.
- The `img` tag uses `lazy loading` for better performance, especially with large images.

---

## Display All Files with YouTube Filter

If you want to filter and display YouTube videos from your collection, use the following example:

```php
@foreach(sGallery::collections()->get() as $item)
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

### Explanation:

- `sGallery::hasYoutube($item->type)` filters out YouTube video files.
- The `iframe` tag embeds the YouTube video into your page.

---

## Display Product Gallery

To display all media files associated with a specific product, you can filter by `documentId` and `itemType`.
This is particularly useful for eCommerce or product catalog pages.

```php
@foreach(sGallery::collections()->documentId($product->id)->itemType('product')->get() as $item)
    <div class="swiper-slide">
        <a class="js-trigger-fancybox" href="{% raw %}{{$item->src}}{% endraw %}" data-fancybox="product-gallery">
            <img loading="lazy" src="{% raw %}{{$item->src}}{% endraw %}" width="440" height="440" />
        </a>
    </div>
@endforeach
```

### Explanation:

- `documentId($product->id)` specifies the product's ID, filtering the media files that belong to this specific product.
- `itemType('product')` further filters files by type (in this case, "product").
- Files are displayed in a swiper slider, and clicking on an image opens a fancybox gallery.

---

## Display First Product Image

If you only want to display the first media file from a product's gallery (e.g., a cover image), use the `eq(1)` method:

```php
@php($item = sGallery::collections()->documentId($product->id)->itemType('product')->eq(1))
<img loading="lazy" src="{% raw %}{{$item->src}}{% endraw %}" alt="{% raw %}{{$item->alt}}{% endraw %}" width="440" height="440" />
```

### Explanation:

- `eq(1)` retrieves the first item in the collection.
- This is ideal for displaying a product cover image or a preview.

---

## Display Product Cover with Full Filter

This example shows how to retrieve a specific file from a gallery by applying multiple filters, such as block name, language, and item type.

```php
@php($item = sGallery::collections()->documentId($product->id)->itemType('product')->blockName('photo')->lang('en')->eq(1))
<img loading="lazy" src="{% raw %}{{$item->src}}{% endraw %}" alt="{% raw %}{{$item->alt}}{% endraw %}" width="440" height="440" />
```

### Explanation:

- `blockName('photo')` specifies the gallery block (e.g., 'photo').
- `lang('en')` filters the media based on the language, if needed.
- The `eq(1)` method still retrieves the first item from the filtered collection.

---

## Managing Multiple Galleries on the Same Page

If you need to display multiple galleries on a single page, use the `blockName()` method to filter files for each
specific gallery block.

```php
@foreach(sGallery::collections()->blockName('cinema')->get() as $item)
    @if(sGallery::hasYoutube($item->type))
        <lite-youtube videoid="{% raw %}{{$item->file}}{% endraw %}"></lite-youtube>
    @endif
@endforeach
```

### Explanation:

- `blockName('cinema')` ensures that only the files belonging to the "cinema" block are shown.
- This is useful when you have multiple media galleries on the same page, such as in a portfolio or multi-product page.

---

## Example: Full-Parameter Image Slider for Products

Below is a more complex example showing how to form a product image slider using all available parameters in
the sGallery system:

```php
@foreach(sGallery::collections()->documentId($product->id)->itemType('product')->blockName('photo')->lang('en')->get() as $item)
    @if(sGallery::hasImage($item->type))
        <a class="swiper-slide" @if(trim($item->link)) href="{% raw %}{{$item->link}}{% endraw %}" @endif>
            <div class="container">
                <img loading="lazy" class="intro__img" src="{% raw %}{{$item->src}}{% endraw %}" alt="{% raw %}{{$item->alt}}{% endraw %}" width="1440" height="456">
                <div class="intro__inner">
                    <div class="h1__title">{% raw %}{{$item->title}}{% endraw %}</div>
                    <p class="intro__text">{% raw %}{{$item->description}}{% endraw %}</p>
                    @if(trim($item->link_text)) 
                        <div class="btn background__mod">{% raw %}{{$item->link_text}}{% endraw %}</div> 
                    @endif
                </div>
            </div>
        </a>
    @endif
@endforeach
```

### Explanation:

- This example utilizes the `documentId()`, `itemType()`, `blockName()`, and `lang()` filters to retrieve specific
gallery files for a product.
- The media is displayed in a swiper slider, complete with title, description, and optional link.
