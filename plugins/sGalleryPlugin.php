<?php

use Seiger\sGallery\Controllers\sGalleryController;

Event::listen('evolution.OnDocFormRender', function($params) {
    $sGalleryTemplateConfig = explode(',' , evo()->getConfig('sGalleryTemplateConfig', 0));
    if (in_array($params['template'], $sGalleryTemplateConfig) && $params['id'] > 0) {
        $sGallery = new sGalleryController();
        return $sGallery->index();
    }
});