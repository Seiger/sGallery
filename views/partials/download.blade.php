<li class="card" data-sgallery="{{$gallery->id}}">
    <button type="button" title="@lang('sGallery::manager.edit_text')" class="btn btn-xs btn-primary" data-image-edit="{{$gallery->id}}"><i class="far fa-edit"></i></button>
    <button type="button" title="@lang('sGallery::manager.image_delete')" class="btn btn-xs btn-danger" data-image-remove="{{$gallery->id}}"><i class="fas fa-trash-alt"></i></button>
    @if($url = $gallery->mediaUrl())
        @if($gallery->type === 'audio')
            <audio src="{{$url}}" controls preload="none"></audio>
        @else
            <a href="{{$url}}" download>{{basename($gallery->file)}}</a>
        @endif
    @else
        <span>{{basename($gallery->file)}}</span>
    @endif
</li>
