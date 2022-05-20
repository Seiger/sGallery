<ul class="nav nav-tabs">
    @foreach(sGallery::langTabs() as $lang => $tab)
        <li class="nav-item">
            <button class="nav-link" data-bs-target="#{{$lang}}" type="button">{!! $tab !!}</button>
        </li>
    @endforeach
</ul>
<div class="tab-content">
    @foreach(sGallery::langTabs() as $lang => $tab)
        <div class="tab-pane fade" id="{{$lang}}">
            <br>
            <div class="row-col col-12">
                <div class="row form-row">
                    <div class="input-group col">
                        <span class="input-group-text">alt</span>
                        <input type="text" name="list[{{$key}}][{{$lang}}][alt]" class="form-control" value="{{$items[$lang]->alt ?? ''}}">
                    </div>
                </div>
            </div>
            <div class="split my-1"></div>
            <div class="row-col col-12">
                <div class="row form-row">
                    <div class="input-group col">
                        <span class="input-group-text">title</span>
                        <input type="text" name="list[{{$key}}][{{$lang}}][title]" class="form-control" value="{{$items[$lang]->title ?? ''}}">
                    </div>
                </div>
            </div>
            <div class="split my-1"></div>
            <div class="row-col col-12">
                <div class="row form-row">
                    <div class="input-group col">
                        <span class="input-group-text">description</span>
                        <input type="text" name="list[{{$key}}][{{$lang}}][description]" class="form-control" value="{{$items[$lang]->description ?? ''}}">
                    </div>
                </div>
            </div>
            <div class="split my-1"></div>
            <div class="row-col col-12">
                <div class="row form-row">
                    <div class="input-group col">
                        <span class="input-group-text">link text</span>
                        <input type="text" name="list[{{$key}}][{{$lang}}][link_text]" class="form-control" value="{{$items[$lang]->link_text ?? ''}}">
                    </div>
                </div>
            </div>
            <div class="split my-1"></div>
            <div class="row-col col-12">
                <div class="row form-row">
                    <div class="input-group col">
                        <span class="input-group-text">link</span>
                        <input type="text" name="list[{{$key}}][{{$lang}}][link]" class="form-control" value="{{$items[$lang]->link ?? ''}}">
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>