<div class="tab-page galleryTab" id="templateTab">
    <h2 class="tab"><span><i class="fas fa-photo-video"></i> @lang('sGallery::manager.gallery')</span></h2>
    <script>tpResources.addTabPage(document.getElementById("galleryTab"));</script>

    <div class="btn-group btn-group-sm">
        <input type="file" id="filesToUpload" name="files[]" multiple hidden/>
        <label for="filesToUpload" class="btn btn-secondary" style="margin-bottom:0;">
            <i class="fas fa-file-upload"></i> <span>@lang('sGallery::manager.file_upload')</span>
        </label>

        <button class="btn btn-secondary">
            <i class="fab fa-youtube"></i> <span>@lang('sGallery::manager.add_youtube')</span>
        </button>
    </div>

    <ul id="uploadBase">
        @foreach($galleries as $gallery)
            @switch($gallery->type)
                @case(\Seiger\sGallery\Models\sGalleryModel::TYPE_IMAGE)
                    @include('sGallery::partials.image')
                    @break
            @endswitch
        @endforeach
    </ul>
</div>

@push('scripts.bot')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script>
        /* Sorting */
        var uploadBase = document.getElementById('uploadBase');
        var sortable = new Sortable.create(uploadBase, {
            animation: 150,
            onSort: function (evt) {
                doResorting(evt);
            }
        });

        async function doResorting(e) {
            let list = new FormData();
            document.querySelectorAll('#uploadBase > li').forEach(item => list.append('item[]', item.getAttribute('data-sgallery')));
            await fetch('{{route('sGallery.sort', ['cat' => request()->get('id')])}}', {method: 'POST', body: list});
        }

        /* Upload Images */
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
            let resp = await fetch('{{route('sGallery.upload', ['cat' => request()->get('id')])}}', {
                method: 'POST',
                body: form
            });
            let data = await resp.json();
            console.log(`Done with ${f.name}`);
            var uploadBase = document.getElementById('uploadBase');
            uploadBase.insertAdjacentHTML('beforeend', '<li>' + data.preview + '</li>');
            return data;
        }
    </script>
    <style>
        #uploadBase{margin-top:15px;}
        #uploadBase .image{position:relative;width:250px;height:180px;margin:0 5px 15px 0;list-style:none;display:inline-block;}
        #uploadBase .image > .btn-danger{position:absolute;top:5px;right:5px;display:none;}
        #uploadBase .image:hover > .btn-danger, #uploadBase .image:hover > .btn-primary{display:inline;z-index:100;}
        #uploadBase .image > .btn-primary{position:absolute;top:5px;left:5px;display:none;}
        #uploadBase .image > .form-control, #uploadBase .image > div > .form-control{margin:0 0px -17px 0;}
        #uploadBase .image > i.type {
            position: absolute;
            top: auto;
            bottom: 5px;
            right: 15px;
            display: block;
            margin: 0;
            color: #ffffff;
            text-shadow: 0 0 3px rgba(0,0,0,1);
        }
        #uploadBase .image > i.play_button {
            position: absolute;
            top: calc(50% - 35px);
            right: calc(50% - 35px);
            display: block;
            margin: 0;
            color: #ffffff;
            opacity: 0.5;
            text-shadow: 0 0 5px rgba(0,0,0,1);
            cursor: pointer;
            -webkit-transform: rotate(0deg);
            -ms-transform: rotate(0deg);
            transform: rotate(0deg);
            -webkit-transform-origin: center;
            -ms-transform-origin: center;
            transform-origin: center;
            -webkit-transition: all 0.5s;
            -o-transition: all 0.5s;
            transition: all 0.5s;
        }
        #uploadBase .image > i.play_button.fa-pause-circle-o {
            opacity: 0.1;
        }
        #uploadBase .image:hover > i.play_button.fa-play-circle-o {
            -webkit-transform: rotate(120deg);
            -ms-transform: rotate(120deg);
            transform: rotate(120deg);
            opacity: 1;
        }
        #uploadBase .image:hover > i.play_button.fa-pause-circle-o {
            opacity: 1;
        }
        #uploadBase .image > i.youtube_button.play {
            background: url('/<?php echo $path; ?>/images/youtube-logo.png') no-repeat;
            background-size: contain;
            position: absolute;
            top: calc(50% - 30px);
            right: calc(50% - 35px);
            width: 70px;
            height: 70px;
            cursor: pointer;
            transition: scale 0.5s;
        }
        #uploadBase .image:hover > i.youtube_button.play {
            transform: scale(1.1);
        }
        #uploadBase .image > i.youtube_button.fa-pause-circle-o {
            position: absolute;
            top: calc(50% - 30px);
            right: calc(50% - 30px);
            transition: opacity 0.5s;
            opacity: 0.2;
        }
        #uploadBase .image:hover > i.youtube_button.fa-pause-circle-o {
            opacity: 1;
            color: white;
            cursor: pointer;
        }
        iframe.thumbnail {
            pointer-events: none;
        }
    </style>
@endpush