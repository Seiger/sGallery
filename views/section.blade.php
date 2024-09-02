<section>
    <div class="btn-group btn-group-sm">
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
</section>

@push('scripts.bot')
    @include('sGallery::partials.scripts', [
        'typeId' => $sGalleryController->getIdType(),
        'resourceType' => $sGalleryController->getResourceType(),
        'blockId' => $sGalleryController->getBlockNameId(),
        'blockName' => $sGalleryController->getBlockName()
    ])
@endpush