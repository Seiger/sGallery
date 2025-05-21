<script src="https://cdn.tailwindcss.com"></script>
<style type="text/tailwindcss">
    @layer components {
        .label {@apply block text-sm font-medium text-gray-700}
        .field-wrap {@apply flex items-center space-x-4 mb-4}
        .input-field {@apply mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500}
    }
    @layer utilities {}
</style>
<ul class="nav nav-tabs">
    @foreach(sGallery::langTabs() as $lang => $tab)
        <li class="nav-item">
            <button @class(['nav-link', 'active' => $loop->index == 0]) data-target="#{{$lang}}" type="button">{!!$tab!!}</button>
        </li>
    @endforeach
</ul>
<div class="tab-content mt-3" id="fileLangTabs">
    @foreach(sGallery::langTabs() as $lang => $tab)
        <div @class(['tab-pane', 'show' => $loop->index == 0]) id="{{$lang}}">
            <div class="form-group mb-3">
                <label for="alt-{{$lang}}">Alt</label>
                <input type="text" id="alt-{{$lang}}" name="list[{{$key}}][{{$lang}}][alt]" class="form-control" value="{{$items[$lang]->alt ?? ''}}">
            </div>
            <div class="form-group mb-3">
                <label for="title-{{$lang}}">Title</label>
                <input type="text" id="title-{{$lang}}" name="list[{{$key}}][{{$lang}}][title]" class="form-control" value="{{$items[$lang]->title ?? ''}}">
            </div>
            <div class="form-group mb-3">
                <label for="description-{{$lang}}">Description</label>
                <textarea id="description-{{$lang}}" rows="3" name="list[{{$key}}][{{$lang}}][description]" class="form-control">{{$items[$lang]->description ?? ''}}</textarea>
            </div>
            <div class="form-group mb-3">
                <label for="link_text-{{$lang}}">Button Text</label>
                <input type="text" id="link_text-{{$lang}}" name="list[{{$key}}][{{$lang}}][link_text]" class="form-control" value="{{$items[$lang]->link_text ?? ''}}">
            </div>
            <div class="form-group mb-3">
                <label for="link-{{$lang}}">Link</label>
                <input type="text" id="link-{{$lang}}" name="list[{{$key}}][{{$lang}}][link]" class="form-control" value="{{$items[$lang]->link ?? ''}}">
            </div>
        </div>
    @endforeach
</div>
