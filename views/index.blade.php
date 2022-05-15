<div class="tab-page galleryTab" id="templateTab">
    <h2 class="tab"><span><i class="fas fa-photo-video"></i> @lang('sGallery::manager.gallery')</span></h2>
    <script>tpResources.addTabPage(document.getElementById("galleryTab"));</script>

    <div class="btn-group btn-group-sm">
        <input type="file" id="filesToUpload" name="files[]" multiple hidden/>
        <label for="filesToUpload" class="btn btn-secondary" style="margin-bottom:0;">
            <i class="fas fa-image"></i> <span>@lang('sGallery::manager.image_upload')</span>
        </label>

        <button class="btn btn-secondary">
            <i class="fab fa-youtube"></i> <span>@lang('sGallery::manager.add_youtube')</span>
        </button>
    </div>

    <form id="imgForm" enctype="multipart/form-data" method="post">
        <ul id="uploadBase"></ul>
    </form>

</div>

@push('scripts.bot')
    <script>
        document.querySelector('#filesToUpload').addEventListener('change', event => {
            doUpload(event);
        });

        async function doUpload(e) {
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
            let resp = await fetch('{{route('sGallery.upload', ['cat' => request()->get('id')])}}', {method: 'POST', body:form});
            let data = await resp.json();
            console.log(`Done with ${f.name}`);
            var uploadBase = document.getElementById('uploadBase');
            uploadBase.insertAdjacentHTML('beforeend', '<li>'+data.preview+'</li>');
            return data;
        }
    </script>
@endpush