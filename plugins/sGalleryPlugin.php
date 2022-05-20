<?php

use Seiger\sGallery\Controllers\sGalleryController;

Event::listen('evolution.OnDocFormRender', function($params) {
    if (in_array($params['template'], evo()->getConfig('sgallery', [0])) && $params['id'] > 0) {
        $sGallery = new sGalleryController();
        return $sGallery->index();
    }
});