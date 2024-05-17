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

    <div class="modal fade" id="translate{{$sGalleryController->getBlockNameId()}}" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">@lang('sGallery::manager.texts_for_file') <span class="filemane"></span></div>
                <div class="modal-body"></div>
                <div class="modal-footer">
                    <span class="btn btn-success" onclick="sendForm{{$sGalleryController->getBlockNameId()}}('#translate{{$sGalleryController->getBlockNameId()}}');">@lang('sGallery::manager.save')</span>
                    <span class="btn btn-default" onclick="$('#translate{{$sGalleryController->getBlockNameId()}}').hide();">@lang('sGallery::manager.cancel')</span>
                </div>
            </div>
        </div>
    </div>
</section>

@push('scripts.bot')
    <script>
        /* Upload Images */
        document.querySelector('#filesToUpload{{$sGalleryController->getBlockNameId()}}').addEventListener('change', event => {
            window.parent.document.getElementById('mainloader').classList.add('show');
            doUploadFile{{$sGalleryController->getBlockNameId()}}(event);
        });

        async function doUploadFile{{$sGalleryController->getBlockNameId()}}(e) {
            e.preventDefault();

            const files = e.target.files;
            let totalFilesToUpload = files.length;

            //nothing was selected
            if (totalFilesToUpload === 0) {
                return;
            }

            let uploads = [];
            for (let i = 0; i < totalFilesToUpload; i++) {
                uploads.push(uploadFile{{$sGalleryController->getBlockNameId()}}(files[i]));
            }

            await Promise.all(uploads);
        }

        async function uploadFile{{$sGalleryController->getBlockNameId()}}(f) {
            console.log(`Starting with ${f.name}`);
            let form = new FormData();
            form.append('file', f);
            let resp = await fetch('{{route('sGallery.upload-file', [
                'cat' => request()->get($sGalleryController->getIdType()),
                'resourceType' => $sGalleryController->getResourceType(),
                'block' => $sGalleryController->getBlockName()
            ])}}', {
                method: 'POST',
                body: form
            });
            let data = await resp.json();
            console.log(`Done with ${f.name}`);
            if (data.success == 0) {
                alertify.alert('@lang('sGallery::manager.file_upload_error')', data.error);
            } else {
                document.getElementById('uploadBase{{$sGalleryController->getBlockNameId()}}').insertAdjacentHTML('beforeend', data.preview);
            }
            window.parent.document.getElementById('mainloader').classList.remove('show');
            doResorting{{$sGalleryController->getBlockNameId()}}();
            return data;
        }
    </script>
    @include('sGallery::partials.scripts', ['blockId' => $sGalleryController->getBlockNameId()])
@endpush