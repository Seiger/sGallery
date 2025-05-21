<div class="flex flex-wrap gap-2">
    <label id="browseImage{{$blockId}}" class="sg-btn sg-btn-blue">
        <i class="fas fa-camera"></i>
        <span>@lang('sGallery::manager.image_library')</span>
    </label>
    <label for="filesToUpload{{$blockId}}" class="sg-btn sg-btn-blue">
        <i class="fas fa-file-upload"></i>
        <span>@lang('sGallery::manager.file_upload')</span>
    </label>
    <button id="addYoutube{{$blockId}}" type="button" class="sg-btn sg-btn-red">
        <i class="fab fa-youtube"></i>
        <span>@lang('sGallery::manager.add_youtube')</span>
    </button>
    <input id="filesToUpload{{$blockId}}" type="file" name="files[]" multiple hidden/>
</div>