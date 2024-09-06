<div class="tab-page galleryTab" id="templateTab">
    <h2 class="tab"><span><i class="fas fa-photo-video"></i> @lang('sGallery::manager.gallery') @if ($sGalleryController->getBlockName() != 1){{$sGalleryController->getBlockName()}}@endif</span></h2>

    <div class="btn-group btn-group-sm" style="margin-left:1rem;">
        <input type="file" id="filesToUpload{{$sGalleryController->getBlockNameId()}}" name="files[]" multiple hidden/>
        <label for="filesToUpload{{$sGalleryController->getBlockNameId()}}" class="btn btn-secondary" style="margin-bottom:0;">
            <i class="fas fa-file-upload"></i> <span>@lang('sGallery::manager.file_upload')</span>
        </label>

        <button id="addYoutube{{$sGalleryController->getBlockNameId()}}" class="btn btn-secondary">
            <i class="fab fa-youtube"></i> <span>@lang('sGallery::manager.add_youtube')</span>
        </button>
    </div>

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
        'blockName' => $sGalleryController->getBlockName()
    ])
@endpush