<li class="image">
    <button type="button" title="@lang('sGallery::manager.edit_text')" class="btn btn-xs btn-primary" data-image-edit="{{basename($filepath)}}"><i class="far fa-edit"></i></button>
    <button type="button" title="@lang('sGallery::manager.image_delete')" class="btn btn-xs btn-danger" data-image-remove="{{basename($filepath)}}"><i class="fas fa-trash-alt"></i></button>
    <img src="{{$filepath}}" alt="{{$filepath}}" class="thumbnail" />
    <i class="type fa fa-file-image-o fa-2x"></i>
    <input type="hidden" name="sgallery[{{basename($filepath)}}]" value="image" class="form-control" />
</li>