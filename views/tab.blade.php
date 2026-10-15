<div class="tab-page galleryTab" id="templateTab">
    <h2 class="tab">
        <span>
            <i class="fas fa-photo-video"></i>
            @lang('sGallery::manager.gallery')
            @if($sGalleryController->getBlockName() != 1)
                <strong>{{$sGalleryController->getBlockName()}}</strong>
            @endif
        </span>
    </h2>
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
</div>
@push('scripts.bot')
    @include(\Seiger\sGallery\Services\GallerySettings::view('partials.scripts'), [
        'typeId' => $sGalleryController->getIdType(),
        'itemType' => $sGalleryController->getItemType(),
        'blockId' => $sGalleryController->getBlockNameId(),
        'blockName' => $sGalleryController->getBlockName(),
    ])
@endpush