<li class="card" data-sgallery="{{$gallery->id}}">
    <button type="button" title="@lang('sGallery::manager.edit_text')" class="btn btn-xs btn-primary" data-image-edit="{{$gallery->id}}"><i class="far fa-edit"></i></button>
    <button type="button" title="@lang('sGallery::manager.image_delete')" class="btn btn-xs btn-danger" data-image-remove="{{$gallery->id}}"><i class="fas fa-trash-alt"></i></button>
    <button type="button" class="btn btn-xs btn-primary btn-modal" data-modal="open-modal"><i class="fas fa-expand-arrows-alt"></i></button>
    <img src="{{sGallery::file($gallery->path)->fit(sGallery::defaultFit(), sGallery::defaultWidth(), sGallery::defaultHeight())}}" alt="{{$gallery->file}}" class="object-cover w-full h-full max-w-full rounded" />
    <i class="type fa fa-file-image fa-2x"></i>
</li>