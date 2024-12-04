<div class="btn-group btn-group-sm">
    <label for="filesToUpload{{$blockId}}" class="btn btn-secondary" style="margin-bottom:0;">
        <i class="fas fa-file-upload" style="color:#0b78ff;"></i> <span>@lang('sGallery::manager.file_upload')</span>
    </label>
    <button id="addYoutube{{$blockId}}" class="btn btn-secondary">
        <i class="fab fa-youtube" style="color:#0b78ff;"></i> <span>@lang('sGallery::manager.add_youtube')</span>
    </button>
    <label id="browseImage{{$blockId}}" class="btn btn-secondary" style="margin-bottom:0;">
        <i class="fas fa-camera" style="color:#0b78ff;"></i> <span>@lang('sGallery::manager.іmage_library')</span>
    </label>
    <input id="filesToUpload{{$blockId}}" type="file" name="files[]" multiple hidden/>
</div>