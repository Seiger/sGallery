<section class="files">
    <div class="btn-group btn-group-sm">
        <input type="file" id="filesToUploadDownload" name="files[]" multiple hidden/>
        <label for="filesToUploadDownload" class="btn btn-secondary" style="margin-bottom:0;">
            <i class="fas fa-file-upload"></i> <span>@lang('sGallery::manager.file_upload')</span>
        </label>
    </div>

    <ul id="uploadBase">
        @foreach($galleries as $gallery)
            @include('sGallery::partials.download')
        @endforeach
    </ul>

    <div class="modal fade" id="translate" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">@lang('sGallery::manager.texts_for_file') <span class="filemane"></span></div>
                <div class="modal-body"></div>
                <div class="modal-footer">
                    <span class="btn btn-success" onclick="sendForm('#translate');">@lang('sGallery::manager.save')</span>
                    <span class="btn btn-default" onclick="$('#translate').hide();">@lang('sGallery::manager.cancel')</span>
                </div>
            </div>
        </div>
    </div>
</section>
