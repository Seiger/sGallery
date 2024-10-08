<?php

use Seiger\sGallery\Facades\sGallery;
use Seiger\sGallery\Models\sGalleryModel;

Event::listen('evolution.OnDocFormRender', function($params) {
    $currentTemplate = $params['template'];
    $configs = config('seiger.settings.sGallery', [0]);
    $templateIDs = [];
    $templates = [];

    foreach ($configs as $config) {
        if (is_array($config)) {
            $id = array_key_first($config);
            $templateIDs[] = $id;
            $templates[$id] = $config;
        } elseif (is_int($config)) {
            $templateIDs[] = $config;
            $templates[$config] = $config;
        }
    }

    if (in_array($currentTemplate, $templateIDs) && $params['id'] > 0) {
        if (is_array($templates[$currentTemplate]) && is_array($templates[$currentTemplate][$currentTemplate]) && count($templates[$currentTemplate][$currentTemplate]) > 0) {
            foreach ($templates[$currentTemplate][$currentTemplate] as $block) {
                echo sGallery::initialiseView()->viewType(sGalleryModel::VIEW_TAB)->idType('id')->blockName($block);
            }
        } else {
            echo sGallery::initialiseView()->viewType(sGalleryModel::VIEW_TAB)->idType('id');
        }
    }
});