<section class="mt-2 relative">
    @include(\Seiger\sGallery\Services\GallerySettings::view('partials.buttons'), [
        'blockId' => $sGalleryController->getBlockNameId(),
    ])
    <ul class="grid-gallery" id="uploadBase{{$sGalleryController->getBlockNameId()}}">
        @foreach($galleries as $gallery)
            @includeIf(\Seiger\sGallery\Services\GallerySettings::view('partials.'.$gallery->type))
        @endforeach
    </ul>
    @include(\Seiger\sGallery\Services\GallerySettings::view('partials.slider'), [
        'blockId' => $sGalleryController->getBlockNameId(),
        'galleries' => $galleries,
    ])
</section>
@push('scripts.bot')
    @include(\Seiger\sGallery\Services\GallerySettings::view('partials.scripts'), [
        'typeId' => $sGalleryController->getIdType(),
        'itemType' => $sGalleryController->getItemType(),
        'blockId' => $sGalleryController->getBlockNameId(),
        'blockName' => $sGalleryController->getBlockName(),
    ])
@endpush