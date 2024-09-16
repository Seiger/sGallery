<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.14.0/build/css/alertify.min.css"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.14.0/build/css/themes/bootstrap.min.css"/>
<style>
    #uploadBase{{$blockId}} {margin-top:15px;}
    #uploadBase{{$blockId}} .image{position:relative;width:240px;height:120px;margin:0 5px 5px 0;list-style:none;display:inline-block;}
    #uploadBase{{$blockId}} .image > .btn-danger{position:absolute;top:5px;right:5px;display:none;}
    #uploadBase{{$blockId}} .image:hover > .btn-danger, #uploadBase{{$blockId}} .image:hover > .btn-primary{display:inline;z-index:100;}
    #uploadBase{{$blockId}} .image > .btn-primary{position:absolute;top:5px;left:5px;display:none;}
    #uploadBase{{$blockId}} .image > .form-control, #uploadBase{{$blockId}} .image > div > .form-control{margin:0 0px -17px 0;}
    #uploadBase{{$blockId}} .image > i.type{position:absolute;top:auto;bottom:5px;right:10px;display:block;margin:0;color:#ffffff;text-shadow:0 0 3px rgba(0,0,0,1);}
    #uploadBase{{$blockId}} .image > i.play_button{position:absolute;top:calc(50% - 35px);right:calc(50% - 35px);display:block;margin:0;color:#ffffff;opacity:0.5;text-shadow:0 0 5px rgba(0,0,0,1);cursor:pointer;-webkit-transform:rotate(0deg);-ms-transform:rotate(0deg);transform:rotate(0deg);-webkit-transform-origin:center;-ms-transform-origin:center;transform-origin:center;-webkit-transition:all 0.5s;-o-transition:all 0.5s;transition:all 0.5s;}
    #uploadBase{{$blockId}} .image > i.play_button.fa-pause-circle-o{opacity:0.1;}
    #uploadBase{{$blockId}} .image:hover > i.play_button.fa-play-circle-o{-webkit-transform:rotate(120deg);-ms-transform:rotate(120deg);transform:rotate(120deg);opacity:1;}
    #uploadBase{{$blockId}} .image:hover > i.play_button.fa-pause-circle-o{opacity:1;}
    #uploadBase{{$blockId}} .image > i.youtube_button.play{background:url('{{\Seiger\sGallery\Models\sGalleryModel::UPLOADED}}youtube-logo.png') no-repeat;background-size:contain;position:absolute;top:calc(50% - 35px);right:calc(50% - 35px);width:70px;height:70px;cursor:pointer;transition:scale 0.5s;}
    #uploadBase{{$blockId}} .image:hover > i.youtube_button.play{transform:scale(1.1);}
    #uploadBase{{$blockId}} .image > i.youtube_button.fa-pause-circle-o{position:absolute;top:calc(50% - 35px);right:calc(50% - 35px);transition:opacity 0.5s;opacity:0.2;}
    #uploadBase{{$blockId}} .image:hover > i.youtube_button.fa-pause-circle-o{opacity:1;color:white;cursor:pointer;}
    #uploadBase{{$blockId}} .image img{margin-bottom:34px;}
    #uploadBase{{$blockId}} .image video, #uploadBase{{$blockId}} .image iframe{margin-bottom:-40px;object-fit:cover;}
    #uploadBase{{$blockId}} .image > span.title{position:absolute;top:calc(50%);right:calc(50%);display:block;margin:0;text-shadow:0 0 1px rgba(0,0,0,1);}
    .badge.bg-seigerit-gallery{background-color:#0057b8 !important;color:#ffd700;font-size:85%;}
    .alertify .ajs-header{user-select:none;}
    .alertify .ajs-footer .ajs-buttons .ajs-button.ajs-ok{color:#fff;background-color:#d9534f;border-color:#d9534f;}
    .alertify .ajs-footer .ajs-buttons .ajs-button.ajs-ok-green{color:#fff;background-color:#28a745;border-color:#28a745;}
    .alertify.sgallery-modal .ajs-body .ajs-content{padding:10px 0 0;}
    .ajs-content .form-group{margin-bottom:1rem;}
    .ajs-content .form-control{width:100%;padding:0.375rem 0.75rem;font-size:1rem;line-height:1.5;color:#495057;background-color:#f8f9fa;background-clip:padding-box;border:1px solid #ced4da;border-radius:0.25rem;transition:border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;resize:none;}
    .ajs-content .input-group-text{background-color:#e9ecef;border:1px solid #ced4da;border-radius:0.25rem 0 0 0.25rem;padding:0.375rem 0.75rem;}
    .ajs-content .tab-pane{border-radius:0.25rem;}
    .ajs-content .nav-tabs{border-bottom:1px solid #dee2e6;}
    .ajs-content .nav-item{margin-bottom:-1px;}
    .ajs-content .nav-link{border:1px solid transparent;border-top-left-radius:0.25rem;border-top-right-radius:0.25rem;color:#6c757d;background-color:#f8f9fa;transition:background-color 0.3s ease, color 0.3s ease;}
    .ajs-content .nav-link:hover{color:#495057;background-color:#e9ecef;}
    .ajs-content .nav-link.active{color:#495057;background-color:#fff;border-color:#dee2e6 #dee2e6 #fff;}
</style>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.14.0/build/alertify.min.js"></script>
<script>
    // Initialize sorting for the gallery images
    const uploadBase{{$blockId}} = document.getElementById('uploadBase{{$blockId}}');
    const sortableInstance{{$blockId}} = new Sortable(uploadBase{{$blockId}}, {
        animation: 150, // Animation speed
        onSort: function (e) {
            doResorting{{$blockId}}(); // Call sorting function after reorder
        }
    });

    // Initialize upload buttons for the gallery images
    document.addEventListener("change", event => {
        if (event.target && event.target.id === "filesToUpload{{$blockId}}") {
            window.parent.document.getElementById('mainloader').classList.add('show');
            doUploadFile{{$blockId}}(event);
        } else if(event.target && event.target.id === "filesToUploadDownload{{$blockId}}") {
            window.parent.document.getElementById('mainloader').classList.add('show');
            doUploadDownload{{$blockId}}(event);
        }
    });

    // Handle various click events
    document.addEventListener("click", function(event) {
        if (event.target) {
            let target = event.target;

            if (Boolean(target.closest('button')?.hasAttribute("data-image-edit"))) {
                editImage(event);
            } else if (Boolean(target.closest('button')?.hasAttribute("data-image-remove"))) {
                removeImage(event);
            } else if (target.closest('button')?.id === "addYoutube{{$blockId}}") {
                addYoutubeVideo(event);
            } else if (target.matches("i.play_button")) {
                toggleVideoPlay(event);
            } else if (target.closest('button')?.hasAttribute("data-target")) {
                switchTab(event);
            }
        }
    });

    /* Save new positions */
    async function doResorting{{$blockId}}() {
        let list = new FormData();
        document.querySelectorAll('#uploadBase{{$blockId}} > li').forEach(item => list.append('item[]', item.getAttribute('data-sgallery')));
        await fetch('{!!sGallery::route('sGallery.resort', ['cat' => request()->get($typeId), 'itemType' => $itemType, 'block' => $blockName])!!}', {method: 'POST', body: list});
    }

    async function doUploadFile{{$blockId}}(e) {
        e.preventDefault();
        const files = e.target.files;
        let totalFilesToUpload = files.length;
        // Nothing was selected
        if (totalFilesToUpload === 0) {
            return;
        }
        let uploads = [];
        for (let i = 0; i < totalFilesToUpload; i++) {
            uploads.push(uploadFile{{$blockId}}(files[i]));
        }
        await Promise.all(uploads);
    }

    async function uploadFile{{$blockId}}(f) {
        console.log("Uploading file with name:", f.name);
        let form = new FormData();
        form.append('file', f);
        let resp = await fetch('{!!sGallery::route('sGallery.upload-file', [
                'cat' => request()->get($typeId),
                'itemType' => $itemType,
                'block' => $blockName
            ])!!}', {
            method: 'POST',
            body: form
        });
        let data = await resp.json();
        console.log("Uploaded file with name:", f.name);
        if (data.success == 0) {
            alertify.alert('@lang('sGallery::manager.file_upload_error')', data.error);
        } else {
            uploadBase{{$blockId}}.insertAdjacentHTML('beforeend', data.preview);
        }
        window.parent.document.getElementById('mainloader').classList.remove('show');
        doResorting{{$blockId}}();
        return data;
    }

    async function doUploadDownload{{$blockId}}(e) {
        e.preventDefault();
        const files = e.target.files;
        let totalFilesToUpload = files.length;
        // Nothing was selected
        if (totalFilesToUpload === 0) {
            return;
        }
        let uploads = [];
        for (let i = 0; i < totalFilesToUpload; i++) {
            uploads.push(uploadDownload{{$blockId}}(files[i]));
        }
        await Promise.all(uploads);
    }

    async function uploadDownload{{$blockId}}(f) {
        console.log("Uploading file with name:", f.name);
        let form = new FormData();
        form.append('file', f);
        let resp = await fetch('{!!sGallery::route('sGallery.upload-download', [
                'cat' => request()->get($typeId),
                'itemType' => $itemType,
                'block' => $blockName
            ])!!}', {
            method: 'POST',
            body: form
        });
        let data = await resp.json();
        console.log("Uploaded file with name:", f.name);
        if (data.success == 0) {
            alertify.alert('@lang('sGallery::manager.file_upload_error')', data.error);
        } else {
            uploadBase{{$blockId}}.insertAdjacentHTML('beforeend', data.preview);
        }
        window.parent.document.getElementById('mainloader').classList.remove('show');
        doResorting{{$blockId}}();
        return data;
    }

    // Function to edit image details
    function editImage(e) {
        let itemId = e.target.closest('button').getAttribute("data-image-edit");
        console.log("Editing file with ID:", itemId);

        fetch('{!!sGallery::route('sGallery.gettranslate')!!}' + '?item=' + encodeURIComponent(itemId), {
            method: "GET",
            headers: {
                "Content-Type": "application/json",
            },
            cache: "no-cache"
        })
            .then(response => response.json())
            .then(ajax => {
                alertify.confirm(
                    "@lang('sGallery::manager.texts_for_file')",
                    ajax.tabs, // Content of the modal
                    function() { // This function is called when the "OK" button is clicked
                        sendForm{{$blockId}}("#fileLangTabs");
                        console.log("Confirmed editing for ID:", itemId);
                        document.querySelector('.ajs-ok').classList.remove('ajs-ok-green');
                        document.querySelector('.alertify').classList.remove('sgallery-modal');
                    },
                    function() { // This function is called when the "Cancel" button is clicked
                        console.log("Editing cancelled for ID:", itemId);
                        document.querySelector('.ajs-ok').classList.remove('ajs-ok-green');
                        document.querySelector('.alertify').classList.remove('sgallery-modal');
                    }
                ).set({
                    labels: {ok:"@lang('sGallery::manager.save')", cancel:"@lang('sGallery::manager.cancel')"},
                    transition: 'zoom',
                    movable: false,
                    closableByDimmer: false,
                    pinnable: false
                });
                document.querySelector('.ajs-ok').classList.add('ajs-ok-green');
                document.querySelector('.alertify ').classList.add('sgallery-modal');
            })
            .catch(error => console.error('Error:', error));
    }

    // Function to remove an image
    async function removeImage(e) {
        let _this = e.target.closest('button');
        let itemId = _this.getAttribute("data-image-remove");
        console.log("Deliting file with ID:", itemId);

        alertify.confirm(
            "@lang('sGallery::manager.are_you_sure')",
            "@lang('sGallery::manager.deleted_irretrievably')",
            function() {
                alertify.success("@lang('sGallery::manager.deleted')");
                fetch('{!!sGallery::route('sGallery.delete')!!}', {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: 'item=' + encodeURIComponent(itemId),
                    cache: "no-cache"
                })
                    .then(response => response.json())
                    .then(data => {
                        let imageElement = _this.closest(".image");
                        if (imageElement) {
                            // Apply fade-out effect and remove the image element after 1 second
                            imageElement.style.transition = "opacity 1s ease";
                            imageElement.style.opacity = 0;
                            setTimeout(() => {
                                imageElement.remove();
                            }, 1000);
                        }
                    })
                    .catch(error => console.error('Error:', error));
            },
            function() {
                alertify.error("@lang('sGallery::manager.cancel')");
            }
        ).set({
            labels: {ok:"@lang('sGallery::manager.delete')", cancel:"@lang('sGallery::manager.cancel')"},
            transition: 'zoom',
            movable: false,
            closableByDimmer: false,
            pinnable: false
        });
        e.preventDefault();
    }

    // Function to add a YouTube video link
    function addYoutubeVideo(e) {
        let youtubeLink = prompt("@lang('sGallery::manager.youtube_link')");
        if (youtubeLink) {
            let url = '{!!sGallery::route('sGallery.addyoutube', [
                'cat' => request()->get($typeId),
                'itemType' => $itemType,
                'block' => $blockName
            ])!!}';
            fetch(url, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: 'youtubeLink=' + encodeURIComponent(youtubeLink),
                cache: "no-cache"
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success == 0) {
                        alertify.alert('@lang('sGallery::manager.file_upload_error')', data.error);
                    } else {
                        uploadBase{{$blockId}}.insertAdjacentHTML('beforeend', data.preview);
                        doResorting{{$blockId}}();
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        e.preventDefault();
    }

    // Function to toggle video play/pause state
    function toggleVideoPlay(e) {
        let video = e.target.parentElement.querySelector('video');
        if (video.paused) {
            e.target.classList.remove('fa-play-circle-o');
            e.target.classList.add('fa-pause-circle-o');
            video.play();
        } else {
            e.target.classList.remove('fa-pause-circle-o');
            e.target.classList.add('fa-play-circle-o');
            video.pause();
        }
        e.preventDefault();
    }

    // Function to switch between tabs
    function switchTab(e) {
        document.getElementById('fileLangTabs').parentNode.querySelectorAll(".nav-link").forEach(function(link) {
            link.classList.remove('active');
        });
        document.querySelectorAll("#fileLangTabs .tab-pane").forEach(function(pane) {
            pane.classList.remove('show');
        });
        e.target.closest('button').classList.add('active');
        let targetTab = document.querySelector(e.target.closest('button').getAttribute('data-target'));
        if (targetTab) {
            targetTab.classList.add('show');
        }
        e.preventDefault();
    }

    // Function to submit a form
    function sendForm{{$blockId}}(selector) {
        let formData = new FormData();
        let inputs = document.querySelectorAll(`${selector} input, ${selector} textarea`);
        inputs.forEach(function(input) {
            formData.append(input.name, input.value);
        });
        fetch('{!!sGallery::route('sGallery.settranslate')!!}', {
            method: "POST",
            body: new URLSearchParams(formData),
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            cache: "no-cache"
        })
            .then(response => response.json())
            .then(ajax => {
                if (ajax.success == 1) {
                    alertify.success("@lang('sGallery::manager.saved_successfully')");
                }
            })
            .catch(error => console.error('Error:', error));
        return false;
    }
</script>
