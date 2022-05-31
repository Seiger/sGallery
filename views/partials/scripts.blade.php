<link rel="stylesheet" href="//cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/bootstrap.min.css"/>
<link rel="stylesheet" href="//cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
<script src="https://www.youtube.com/iframe_api"></script>
<script>
    /* Sorting */
    var uploadBase = document.getElementById('uploadBase');
    var sortable = new Sortable.create(uploadBase, {
        animation: 150,
        onSort: function (evt) {
            doResorting(evt);
        }
    });

    /* Save new positions */
    async function doResorting(e) {
        let list = new FormData();
        document.querySelectorAll('#uploadBase > li').forEach(item => list.append('item[]', item.getAttribute('data-sgallery')));
        await fetch('{{route('sGallery.sort', [
            'cat' => request()->get($sGalleryController->getIdType()),
            'resourceType' => $sGalleryController->getResourceType()
            ])}}', {method: 'POST', body: list});
    }

    $(document).on("click", "i.play_button", function() {
        var video = $(this).parent().find('video').get(0);

        if (video.paused) {
            $(this).removeClass('fa-play-circle-o');
            $(this).addClass('fa-pause-circle-o');
            video.play();
        } else {
            $(this).removeClass('fa-pause-circle-o');
            $(this).addClass('fa-play-circle-o');
            video.pause();
        }
        return false;
    });

    $(document).on("click", "#add_youtube", function() {
        var youtube_link = prompt("@lang('sGallery::manager.youtube_link')");
        $.ajax({
            url:'{{route('sGallery.addyoutube', [
                'cat' => request()->get($sGalleryController->getIdType()),
                'resourceType' => $sGalleryController->getResourceType()
                ])}}',
            type:"GET",
            data:'youtube_link='+youtube_link,
            cache:false,
            success:function(data) {
                document.getElementById('uploadBase').insertAdjacentHTML('beforeend', '<li>' + data.preview + '</li>');
                doResorting();
            }
        });
        return false;
    });

    var player = [];

    $(document).on("click", "i.youtube_button", function() {
        var video = $(this).parent().find('iframe').get(0).id;

        if (player[video].getPlayerState() !== 1 ) {
            $(this).removeClass('play');
            $(this).addClass('fa fa-pause-circle-o fa-5x');
            player[video].playVideo();
        } else {
            $(this).removeClass('fa fa-pause-circle-o fa-5x');
            $(this).addClass('play');
            player[video].pauseVideo();
        }
        return false;
    });

    window.onYouTubeIframeAPIReady = function() {
        $.each($('iframe.thumbnail'), function(index, element) {
            player[element.id] = new YT.Player(element.id);
        });
    }

    /* Delete item */
    $(document).on("click", "[data-image-remove]", function() {
        var _this = $(this);
        alertify.confirm("@lang('sGallery::manager.are_you_sure')", "@lang('sGallery::manager.deleted_irretrievably')",
            function() {
                alertify.success("@lang('sGallery::manager.deleted')");
                $.ajax({
                    url:'{{route('sGallery.delete')}}',
                    type:"POST",
                    data:'item='+_this.attr("data-image-remove"),
                    cache:false,
                    success:function(data) {
                        _this.parents(".image").fadeOut(1000, function() {$(this).remove()});
                    }
                });
            },
            function() {
                alertify.error("@lang('sGallery::manager.cancel')");
            });
        return false;
    });

    $(document).on("click", "[data-image-edit]", function() {
        var _this = $(this);
        $.ajax({
            url:'{{route('sGallery.gettranslate')}}',
            data:'item='+_this.attr("data-image-edit"),
            cache:false,
            success:function(ajax) {
                $("#translate .modal-body").html(ajax.tabs);
                $("#translate .nav-link:first-child").addClass('active');
                $("#translate .tab-pane:first-child").addClass('active').addClass('show');
                $('#translate').show();
            }
        });
        return false;
    });

    $(document).on("click", "#translate [data-bs-target]", function() {
        var tabButton = $(this);

        $("#translate .nav-link").each(function () {
            $(this).removeClass('active');
        });
        $("#translate .tab-pane").each(function () {
            $(this).removeClass('active').removeClass('show');
        });

        tabButton.addClass('active');
        $(tabButton.attr('data-bs-target')).addClass('active').addClass('show');
    });

    function sendForm(selector) {
        $.ajax({
            url:'{{route('sGallery.settranslate')}}',
            type:"POST",
            data:$(document).find(selector).find('input').serialize(),
            cache:false,
            success:function(ajax) {
                if (ajax.success == 1) {
                    $(selector).hide();
                    alertify.success("@lang('sGallery::manager.saved_successfully')");
                }
            }
        });
        return false;
    }
</script>
<style>
    #uploadBase{margin-top:15px;}
    #uploadBase .image{position:relative;width:240px;height:120px;margin:0 5px 5px 0;list-style:none;display:inline-block;}
    #uploadBase .image > .btn-danger{position:absolute;top:5px;right:5px;display:none;}
    #uploadBase .image:hover > .btn-danger, #uploadBase .image:hover > .btn-primary{display:inline;z-index:100;}
    #uploadBase .image > .btn-primary{position:absolute;top:5px;left:5px;display:none;}
    #uploadBase .image > .form-control, #uploadBase .image > div > .form-control{margin:0 0px -17px 0;}
    #uploadBase .image > i.type{position:absolute;top:auto;bottom:5px;right:10px;display:block;margin:0;color:#ffffff;text-shadow:0 0 3px rgba(0,0,0,1);}
    #uploadBase .image > i.play_button{position:absolute;top:calc(50% - 35px);right:calc(50% - 35px);display:block;margin:0;color:#ffffff;opacity:0.5;text-shadow:0 0 5px rgba(0,0,0,1);cursor:pointer;-webkit-transform:rotate(0deg);-ms-transform:rotate(0deg);transform:rotate(0deg);-webkit-transform-origin:center;-ms-transform-origin:center;transform-origin:center;-webkit-transition:all 0.5s;-o-transition:all 0.5s;transition:all 0.5s;}
    #uploadBase .image > i.play_button.fa-pause-circle-o{opacity:0.1;}
    #uploadBase .image:hover > i.play_button.fa-play-circle-o{-webkit-transform:rotate(120deg);-ms-transform:rotate(120deg);transform:rotate(120deg);opacity:1;}
    #uploadBase .image:hover > i.play_button.fa-pause-circle-o{opacity:1;}
    #uploadBase .image > i.youtube_button.play{background:url('{{\Seiger\sGallery\Models\sGalleryModel::UPLOADED}}youtube-logo.png') no-repeat;background-size:contain;position:absolute;top:calc(50% - 35px);right:calc(50% - 35px);width:70px;height:70px;cursor:pointer;transition:scale 0.5s;}
    #uploadBase .image:hover > i.youtube_button.play{transform:scale(1.1);}
    #uploadBase .image > i.youtube_button.fa-pause-circle-o{position:absolute;top:calc(50% - 35px);right:calc(50% - 35px);transition:opacity 0.5s;opacity:0.2;}
    #uploadBase .image:hover > i.youtube_button.fa-pause-circle-o{opacity:1;color:white;cursor:pointer;}
    #uploadBase .image img{margin-bottom:34px;}
    #uploadBase .image video, #uploadBase .image iframe{margin-bottom:-69px;object-fit:cover;}
    #uploadBase .image > span.title{position:absolute;top:calc(50%);right:calc(50%);display:block;margin:0;text-shadow:0 0 1px rgba(0,0,0,1);}
    iframe.thumbnail{pointer-events:none;}
    .modal{top:50px;font-weight:bold;}
    .fade:not(.show){opacity:initial;}
    .modal-backdrop {background-color:rgba(0, 0, 0, 0.5);}
    .modal-header{margin-top:1rem;}
    .badge.bg-seigerit-gallery{background-color:#0057b8;color:#ffd700;font-size:120%;}
</style>