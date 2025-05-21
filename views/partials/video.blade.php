<li class="card" data-sgallery="{{$gallery->id}}">
    <button type="button" title="@lang('sGallery::manager.edit_text')" class="btn btn-xs btn-primary" data-image-edit="{{$gallery->id}}"><i class="far fa-edit"></i></button>
    <button type="button" title="@lang('sGallery::manager.image_delete')" class="btn btn-xs btn-danger" data-image-remove="{{$gallery->id}}"><i class="fas fa-trash-alt"></i></button>
    <video src="{{$gallery->src}}" width='{{sGallery::defaultWidth()}}' height='{{sGallery::defaultHeight()}}' loop></video>
    <i class="type fa fa-file-movie-o fa-2x"></i>
    <i class="play_button fa fa-play-circle fa-5x"></i>
</li>