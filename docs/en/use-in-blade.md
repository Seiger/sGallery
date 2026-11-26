---
id: use-in-blade
title: Using sGallery in Blade Templates
slug: /use-in-blade/
sidebar_position: 4
---

# Using sGallery in Blade Templates

Select a gallery by parent, block and locale. Use the media type to choose markup and Blade's escaped expressions to output URLs and text.

---

## Display Mixed Gallery Items

Use this pattern when one gallery can contain different media types and each needs its own markup.

```php
@foreach(sGallery::collections()->get() as $item)
    @if(sGallery::hasImage($item->type))
        <a class="swiper-slide" @if(trim($item->link)) href="{{$item->link}}" @endif>
            <div class="container">
                <img loading="lazy" class="intro__img" src="{{$item->src}}" alt="{{$item->alt}}" width="1440" height="456">
                <div class="intro__inner">
                    <div class="h1__title">{{$item->title}}</div>
                    <p class="intro__text">{{$item->description}}</p>
                </div>
            </div>
        </a>
    @elseif(sGallery::hasYoutube($item->type))
        <div class="item">
            <div class="video">
                <iframe width="560" height="315" src="https://www.youtube.com/embed/{{$item->file}}" title="YouTube video player" allowfullscreen></iframe>
            </div>
            <p>{{$item->title}}</p>
        </div>
    @endif
@endforeach
```

### Explanation:

- `sGallery::collections()->get()` retrieves the current gallery collection.
- `hasImage()` and `hasYoutube()` choose the correct markup for every loaded item.

---

## Display Images with Query Filter

Use `mediaType()` when the page needs only one media type and should not load other rows.

```php
@foreach(sGallery::collections()->mediaType('image')->get() as $item)
    <a class="swiper-slide" @if(trim($item->link)) href="{{$item->link}}" @endif>
        <div class="container">
            <img loading="lazy" class="intro__img" src="{{$item->src}}" alt="{{$item->alt}}" width="1440" height="456">
            <div class="intro__inner">
                <div class="h1__title">{{$item->title}}</div>
                <p class="intro__text">{{$item->description}}</p>
                @if(trim($item->link_text))
                    <div class="btn background__mod">{{$item->link_text}}</div>
                @endif
            </div>
        </div>
    </a>
@endforeach
```

### Explanation:

- `sGallery::collections()->mediaType('image')->get()` filters image rows in the database before rendering.
- The `img` tag uses `lazy loading` for better performance, especially with large images.

---

## Display All Files with YouTube Filter

If you want to filter and display YouTube videos from your collection, use the following example:

```php
@foreach(sGallery::collections()->mediaType('youtube')->get() as $item)
    <div class="item">
        <div class="video">
            <iframe width="560" height="315" src="https://www.youtube.com/embed/{{$item->file}}" title="YouTube video player" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
        <p>{{$item->title}}</p>
    </div>
@endforeach
```

### Explanation:

- `sGallery::collections()->mediaType('youtube')->get()` filters YouTube rows in the database before rendering.
- The `iframe` tag embeds the YouTube video into your page.

---

## Display Product Gallery

To display all media files associated with a specific product, you can filter by `documentId` and `itemType`.
This is particularly useful for eCommerce or product catalog pages.

```php
@foreach(sGallery::collections()->documentId($product->id)->itemType('product')->get() as $item)
    <div class="swiper-slide">
        <a class="js-trigger-fancybox" href="{{$item->src}}" data-fancybox="product-gallery">
            <img loading="lazy" src="{{$item->src}}" width="440" height="440" />
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
@php($item = sGallery::collections()->documentId($product->id)->itemType('product')->mediaType('image')->eq(1))
@if ($item)
    <img loading="lazy" src="{{ $item->src }}" alt="{{ $item->alt }}" width="440" height="440">
@endif
```

### Explanation:

- `mediaType('image')` restricts the search to images. `eq(1)` returns the first result or `null` for an empty collection.
- This is ideal for displaying a product cover image or a preview.

---

## Display Product Cover with Full Filter

This example shows how to retrieve a specific file from a gallery by applying multiple filters, such as block name, language, and item type.

```php
@php($item = sGallery::collections()->documentId($product->id)->itemType('product')->blockName('photo')->lang('en')->mediaType('image')->eq(1))
@if ($item)
    <img loading="lazy" src="{{ $item->src }}" alt="{{ $item->alt }}" width="440" height="440">
@endif
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
        <lite-youtube videoid="{{$item->file}}"></lite-youtube>
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
        <a class="swiper-slide" @if(trim($item->link)) href="{{$item->link}}" @endif>
            <div class="container">
                <img loading="lazy" class="intro__img" src="{{$item->src}}" alt="{{$item->alt}}" width="1440" height="456">
                <div class="intro__inner">
                    <div class="h1__title">{{$item->title}}</div>
                    <p class="intro__text">{{$item->description}}</p>
                    @if(trim($item->link_text)) 
                        <div class="btn background__mod">{{$item->link_text}}</div>
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
