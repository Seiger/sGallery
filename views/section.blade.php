<section class="mt-2 relative">
    @include('sGallery::partials.buttons', [
        'blockId' => $sGalleryController->getBlockNameId(),
    ])
    <ul class="grid-gallery" id="uploadBase{{$sGalleryController->getBlockNameId()}}">
        @foreach($galleries as $gallery)
            @include('sGallery::partials.'.$gallery->type)
        @endforeach
    </ul>
    @include('sGallery::partials.slider', [
        'blockId' => $sGalleryController->getBlockNameId(),
        'galleries' => $galleries,
    ])
</section>
@push('scripts.bot')
    @include('sGallery::partials.scripts', [
        'typeId' => $sGalleryController->getIdType(),
        'itemType' => $sGalleryController->getItemType(),
        'blockId' => $sGalleryController->getBlockNameId(),
        'blockName' => $sGalleryController->getBlockName(),
    ])
@endpush