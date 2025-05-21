<li class="card" data-sgallery="{{$gallery->id}}">
    <button type="button" title="@lang('sGallery::manager.edit_text')" class="btn btn-xs btn-primary" data-image-edit="{{$gallery->id}}"><i class="far fa-edit"></i></button>
    <button type="button" title="@lang('sGallery::manager.image_delete')" class="btn btn-xs btn-danger" data-image-remove="{{$gallery->id}}"><i class="fas fa-trash-alt"></i></button>
    <iframe id="{{$gallery->file}}"
            width="{{sGallery::defaultWidth()}}"
            height="{{sGallery::defaultHeight()}}"
            src="https://www.youtube.com/embed/{{$gallery->file}}?controls=0&rel=0&showinfo=0&modestbranding=1&fs=0"
            frameborder="0"
            allow="encrypted-media"
            allowfullscreen>
    </iframe>
    <i class="type fa fa-youtube fa-2x"></i>
</li>
