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
    @include('sGallery::partials.buttons', [
        'blockId' => $sGalleryController->getBlockNameId(),
    ])
    <ul id="uploadBase{{$sGalleryController->getBlockNameId()}}">
        @foreach($galleries as $gallery)
            @include('sGallery::partials.'.$gallery->type)
        @endforeach
    </ul>
</div>
@push('scripts.bot')
    @include('sGallery::partials.scripts', [
        'typeId' => $sGalleryController->getIdType(),
        'itemType' => $sGalleryController->getItemType(),
        'blockId' => $sGalleryController->getBlockNameId(),
        'blockName' => $sGalleryController->getBlockName(),
    ])
@endpush