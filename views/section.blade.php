<section>
    <div class="btn-group btn-group-sm">
        <label for="filesToUpload{{$sGalleryController->getBlockNameId()}}" class="btn btn-secondary" style="margin-bottom:0;">
            <i class="fas fa-file-upload" style="color:#0b78ff;"></i> <span>@lang('sGallery::manager.file_upload')</span>
        </label>
        <button id="addYoutube{{$sGalleryController->getBlockNameId()}}" class="btn btn-secondary">
            <i class="fab fa-youtube" style="color:#0b78ff;"></i> <span>@lang('sGallery::manager.add_youtube')</span>
        </button>
        <label id="browseImage{{$sGalleryController->getBlockNameId()}}" class="btn btn-secondary" style="margin-bottom:0;">
            <i class="fas fa-camera" style="color:#0b78ff;"></i> <span>@lang('sGallery::manager.іmage_library')</span>
        </label>
        <input id="filesToUpload{{$sGalleryController->getBlockNameId()}}" type="file" name="files[]" multiple hidden/>
    </div>
    <ul id="uploadBase{{$sGalleryController->getBlockNameId()}}">
        @foreach($galleries as $gallery)
            @include('sGallery::partials.'.$gallery->type)
        @endforeach
    </ul>
</section>
@push('scripts.bot')
    @include('sGallery::partials.scripts', [
        'typeId' => $sGalleryController->getIdType(),
        'itemType' => $sGalleryController->getItemType(),
        'blockId' => $sGalleryController->getBlockNameId(),
        'blockName' => $sGalleryController->getBlockName()
    ])
@endpush