<?php
Event::listen('evolution.OnDocFormRender', function($params) {
    $sPictureTemplateConfig = explode(',' , evo()->getConfig('sGalleryTemplateConfig', 0));
    if (in_array($params['template'], $sPictureTemplateConfig)) {
        echo '<div class="tab-page galleryTab" id="templateTab">';
        echo '<h2 class="tab"><span><i class="fas fa-photo-video"></i> '.__('sGallery::manager.gallery').'</span></h2>';
        echo '<script>tpResources.addTabPage(document.getElementById("galleryTab"));</script>';

        echo '</div>';
    }
});

