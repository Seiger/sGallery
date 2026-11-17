<li class="card" data-sgallery="{{$gallery->id}}">
    <button type="button" title="@lang('sGallery::manager.edit_text')" class="btn btn-xs btn-primary" data-image-edit="{{$gallery->id}}"><i class="far fa-edit"></i></button>
    <button type="button" title="@lang('sGallery::manager.image_delete')" class="btn btn-xs btn-danger" data-image-remove="{{$gallery->id}}"><i class="fas fa-trash-alt"></i></button>
    @if($url = $gallery->mediaUrl())
        <a href="{{$url}}" download><i class="fa fa-file-pdf fa-2x"></i></a>
    @endif
    <span class="title">{{$gallery->file}}</span>
    <i class="type fa fa-file-pdf fa-2x"></i>
</li>
