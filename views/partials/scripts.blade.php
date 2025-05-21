<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.14.0/build/css/alertify.min.css"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.14.0/build/css/themes/bootstrap.min.css"/>
<style>
    #uploadBase{{$blockId}} {margin-top:15px;}
    #uploadBase{{$blockId}} .card > .btn-danger{position:absolute;top:5px;right:5px;display:none;}
    #uploadBase{{$blockId}} .card:hover > .btn-danger, #uploadBase{{$blockId}} .card:hover > .btn-primary{display:inline;z-index:100;}
    #uploadBase{{$blockId}} .card > .btn-primary{position:absolute;top:5px;left:5px;display:none;}
    #uploadBase{{$blockId}} .card > .form-control, #uploadBase{{$blockId}} .card > div > .form-control{margin:0 0px -17px 0;}
    #uploadBase{{$blockId}} .card > i.type{position:absolute;top:auto;bottom:5px;right:10px;display:block;margin:0;color:#ffffff;text-shadow:0 0 3px rgba(0,0,0,1);}
    #uploadBase{{$blockId}} .card > i.play_button{position:absolute;top:calc(50% - 35px);right:calc(50% - 35px);display:block;margin:0;color:#ffffff;opacity:0.5;text-shadow:0 0 5px rgba(0,0,0,1);cursor:pointer;-webkit-transform:rotate(0deg);-ms-transform:rotate(0deg);transform:rotate(0deg);-webkit-transform-origin:center;-ms-transform-origin:center;transform-origin:center;-webkit-transition:all 0.5s;-o-transition:all 0.5s;transition:all 0.5s;}
    #uploadBase{{$blockId}} .card > i.play_button.fa-pause-circle-o{opacity:0.1;}
    #uploadBase{{$blockId}} .card:hover > i.play_button.fa-play-circle-o{-webkit-transform:rotate(120deg);-ms-transform:rotate(120deg);transform:rotate(120deg);opacity:1;}
    #uploadBase{{$blockId}} .card:hover > i.play_button.fa-pause-circle-o{opacity:1;}
    #uploadBase{{$blockId}} .card > i.youtube_button.play{background:url('{{\Seiger\sGallery\Models\sGalleryModel::UPLOADED}}youtube-logo.png') no-repeat;background-size:contain;position:absolute;top:calc(50% - 35px);right:calc(50% - 35px);width:70px;height:70px;cursor:pointer;transition:scale 0.5s;}
    #uploadBase{{$blockId}} .card:hover > i.youtube_button.play{transform:scale(1.1);}
    #uploadBase{{$blockId}} .card > i.youtube_button.fa-pause-circle-o{position:absolute;top:calc(50% - 35px);right:calc(50% - 35px);transition:opacity 0.5s;opacity:0.2;}
    #uploadBase{{$blockId}} .card:hover > i.youtube_button.fa-pause-circle-o{opacity:1;color:white;cursor:pointer;}
    #uploadBase{{$blockId}} .card img{margin-bottom:34px;}
    #uploadBase{{$blockId}} .card video, #uploadBase{{$blockId}} .card iframe{margin-bottom:-40px;object-fit:cover;}
    #uploadBase{{$blockId}} .card > span.title{position:absolute;top:calc(50%);right:calc(50%);display:block;margin:0;text-shadow:0 0 1px rgba(0,0,0,1);}
    .badge.bg-seigerit-gallery{background-color:#0057b8 !important;color:#ffd700;font-size:85%;}
    .alertify .ajs-header{user-select:none;}
    .alertify .ajs-footer .ajs-buttons .ajs-button.ajs-ok{color:#fff;background-color:#d9534f;border-color:#d9534f;}
    .alertify .ajs-footer .ajs-buttons .ajs-button.ajs-ok-green{color:#fff;background-color:#28a745;border-color:#28a745;}
    .alertify .ajs-footer .ajs-buttons .ajs-button.ajs-ok-info{color:#fff;background-color:#5bc0de;border-color:#5bc0de;}
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
<script src="https://cdn.tailwindcss.com"></script>
<style type="text/tailwindcss">
    @layer components {
        .label {@apply block mb-2 text-sm font-medium text-gray-900 dark:text-white}
        .field-wrap {@apply flex items-center space-x-4 mb-4}
        .input-field {@apply bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-600 focus:border-blue-600 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500}
        .btn-t-secondary.btn {@apply !block md:max-w-40 text-white bg-blue-600 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:focus:ring-blue-800 font-medium text-xs px-2 py-1.5 text-center border-transparent !cursor-pointer}
        .btn-t-primary.btn {@apply flex items-center justify-center px-3 py-2 text-sm font-medium text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800}
        .btn-t-secondary > i {@apply me-1 !text-white w-4}
        .btn-transparent {@apply text-gray-900 bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 focus:ring-4 focus:ring-gray-100 font-medium rounded-lg text-sm px-5 py-2.5 me-2 mb-2 dark:bg-gray-800 dark:text-white dark:border-gray-600 dark:hover:bg-gray-700 dark:hover:border-gray-600 dark:focus:ring-gray-700}
        .card {@apply w-[{{sGallery::defaultWidth()}}px] h-[{{sGallery::defaultHeight()}}px] relative overflow-hidden flex-shrink-0 rounded-md;}
        .card img, .card video, .card iframe {@apply w-full h-full object-cover;}
        .grid-gallery {@apply grid gap-4; grid-template-columns: repeat(auto-fill, minmax({{sGallery::defaultWidth()}}px, 1fr)); grid-auto-rows: {{sGallery::defaultHeight()}}px;}
        #uploadBase{{$blockId}} .card .btn-primary.btn-modal{@apply bottom-[5px] top-[initial]}
        .gallery-modal {@apply fixed w-full h-full top-0 left-0 bg-black bg-opacity-30 grid place-items-center z-[2000]}
        .gallery-modal.hidden {@apply opacity-0 pointer-events-none}
        .gallery__slides {@apply flex [transition:transform_0.3s_ease-in-out] bg-black bg-opacity-30}
        .gallery__slider-item {@apply min-w-full box-border grid place-items-center bg-black bg-opacity-30}
        .gallery__slider-item picture {@apply w-full h-full max-w-[90%] max-h-screen}
        .gallery__slider-item img {@apply w-full h-full max-w-full max-h-full object-contain}
        .prev-slide, .next-slide {@apply absolute top-2/4 -translate-y-1/2 text-white}
        .prev-slide {@apply left-[10px]}
        .next-slide {@apply right-[10px]}
        .sg-btn {@apply inline-flex items-center justify-center gap-2 px-2.5 py-1 h-8 text-xs min-w-[7rem] font-medium leading-tight rounded-md border transition-colors duration-200 cursor-pointer select-none appearance-none focus:outline-none focus:ring-2 focus:ring-offset-1 mb-0 align-middle;}
        button.sg-btn, label.sg-btn {border-color: currentColor !important;}
        label.sg-btn {@apply rounded-none;}
        .sg-btn-blue {@apply text-blue-600 bg-white hover:bg-blue-600 hover:text-white focus:ring-blue-500;}
        .sg-btn-red {@apply text-red-600 bg-white hover:bg-red-600 hover:text-white focus:ring-red-500;}
        .sg-btn i {@apply text-sm leading-none;}
    }
    @layer utilities {
        .content-auto {content-visibility: auto;}
    }
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
            } else if (target.closest('label')?.id === "browseImage{{$blockId}}") {
                BrowseServer(target.closest('label').id);
            }
        }
    });

    document.getElementById('browseImage{{$blockId}}').addEventListener('change', event => {
        doEvoLibrary{{$blockId}}(event);
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

    async function doEvoLibrary{{$blockId}}(e) {
        console.log("Past EVO library file:", e.target.value);
        let form = new FormData();
        form.append('file', e.target.value);
        let resp = await fetch('{!!sGallery::route('sGallery.upload-evo-library', [
                'cat' => request()->get($typeId),
                'itemType' => $itemType,
                'block' => $blockName
            ])!!}', {
            method: 'POST',
            body: form
        });
        let data = await resp.json();
        if (data.success == 0) {
            alertify.alert('@lang('sGallery::manager.file_upload_error')', data.error);
        } else {
            uploadBase{{$blockId}}.insertAdjacentHTML('beforeend', data.preview);
        }
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
                        let imageElement = _this.closest(".card");
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
<script>
    const modalBtns = document.querySelectorAll('.btn-modal');
    const modal = document.querySelector('.gallery-modal');
    const modalContent = modal?.querySelector('.modal-content');

    modalBtns?.forEach((el, index) => {
        el.addEventListener('click', () => {
            modal?.classList.remove('hidden');
            slider.goToSlide(index);
        });
    });

    document.addEventListener('click', (event) => {
        if (modal && !modal.classList.contains('hidden')) {
            if (!modalContent?.contains(event.target) && !event.target.closest('.btn-modal')) {
                modal.classList.add('hidden');
            }
        }
    });

    class Slider {
        constructor(selector) {
            this.slider = document.querySelector(selector);
            this.slides = this.slider.querySelector('.gallery__slides');
            this.slideItems = this.slider.querySelectorAll('.gallery__slider-item');
            this.prevButton = this.slider.querySelector('.prev-slide');
            this.nextButton = this.slider.querySelector('.next-slide');
            this.currentIndex = 0;
            this.totalSlides = this.slideItems.length;
            this.init();
        }

        init() {
            this.update();
            this.prevButton.addEventListener('click', () => this.prevSlide());
            this.nextButton.addEventListener('click', () => this.nextSlide());
        }

        update() {
            const offset = -(this.currentIndex * 100);
            this.slides.style.transform = `translateX(${offset}%)`;
        }

        prevSlide() {
            this.currentIndex = (this.currentIndex - 1 + this.totalSlides) % this.totalSlides;
            this.update();
        }

        nextSlide() {
            this.currentIndex = (this.currentIndex + 1) % this.totalSlides;
            this.update();
        }

        goToSlide(index) {
            this.currentIndex = index;
            this.update();
        }
    }

    const slider = new Slider('.gallery__slider{{$blockId}}');
</script>
