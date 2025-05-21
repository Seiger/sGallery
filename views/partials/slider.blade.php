<div class="gallery-modal hidden">
    <span class="btn btn-t-secondary close-modal absolute top-0 right-0" style="z-index:inherit"><i class="fas fa-times text-lg"></i></span>
    <div class="modal-content">
        <div class="gallery__slider{{$blockId}}" data-slider="default">
            <div class="gallery__slides" data-slider="default">
                @foreach($galleries as $gallery)
                    <div class="gallery__slider-item">
                        <picture data-sgallery="">
                            <img src="{{$gallery->src}}" alt="{{$gallery->file}}">
                        </picture>
                    </div>
                @endforeach
            </div>
            <span class="prev-slide"><i class="fas fa-arrow-left text-lg cursor-pointer"></i></span>
            <span class="next-slide"><i class="fas fa-arrow-right text-lg cursor-pointer"></i></span>
        </div>
    </div>
</div>