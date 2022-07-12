<section>
    <div class="btn-group btn-group-sm">
        <input type="file" id="filesToUpload" name="files[]" multiple hidden/>
        <label for="filesToUpload" class="btn btn-secondary" style="margin-bottom:0;">
            <i class="fas fa-file-upload"></i> <span>@lang('sGallery::manager.file_upload')</span>
        </label>

        <button id="add_youtube" class="btn btn-secondary">
            <i class="fab fa-youtube"></i> <span>@lang('sGallery::manager.add_youtube')</span>
        </button>
    </div>

    <ul id="uploadBase">
        @foreach($galleries as $gallery)
            @include('sGallery::partials.'.$gallery->type)
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

@push('scripts.bot')
    <script>
        /* Upload Images */
        document.querySelector('#filesToUpload').addEventListener('change', event => {
            window.parent.document.getElementById('mainloader').classList.add('show');
            doUploadFile(event);
        });

        async function doUploadFile(e) {
            e.preventDefault();

            const files = e.target.files;
            let totalFilesToUpload = files.length;

            //nothing was selected
            if (totalFilesToUpload === 0) {
                return;
            }

            let uploads = [];
            for (let i = 0; i < totalFilesToUpload; i++) {
                uploads.push(uploadFile(files[i]));
            }

            await Promise.all(uploads);
        }

        async function uploadFile(f) {
            console.log(`Starting with ${f.name}`);
            let form = new FormData();
            form.append('file', f);
            let resp = await fetch('{{route('sGallery.upload-file', [
                'cat' => request()->get($sGalleryController->getIdType()),
                'resourceType' => $sGalleryController->getResourceType()
            ])}}', {
                method: 'POST',
                body: form
            });
            let data = await resp.json();
            console.log(`Done with ${f.name}`);
            if (data.success == 0) {
                alertify.alert('@lang('sGallery::manager.file_upload_error')', data.error);
            } else {
                document.getElementById('uploadBase').insertAdjacentHTML('beforeend', data.preview);
            }
            window.parent.document.getElementById('mainloader').classList.remove('show');
            doResorting();
            return data;
        }
    </script>
    @include('sGallery::partials.scripts')
@endpush