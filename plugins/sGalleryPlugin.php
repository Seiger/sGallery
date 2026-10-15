<?php

use Seiger\sGallery\Facades\sGallery;
use Seiger\sGallery\Models\sGalleryModel;

Event::listen('evolution.OnDocFormRender', function($params) {
    $currentTemplate = $params['template'];
    $configs = config('seiger.settings.sGallery', [0]);
    $templates = \Seiger\sGallery\Services\GallerySettings::assignments($configs);
    if (array_key_exists((int)$currentTemplate, $templates) && $params['id'] > 0) {
        if ($templates[(int)$currentTemplate] !== []) {
            foreach ($templates[(int)$currentTemplate] as $block) {
                echo sGallery::initialiseView()->viewType(sGalleryModel::VIEW_TAB)->idType('id')->blockName($block);
            }
        } else {
            echo sGallery::initialiseView()->viewType(sGalleryModel::VIEW_TAB)->idType('id');
        }
    }
});
/** Render the package settings tab through the manager extension event. @since 1.7.0 */
Event::listen('evolution.OnMiscSettingsRender', function () {
    if (!evo()->hasPermission('settings')) return;
    $_SESSION['sgallery_settings_token'] ??= bin2hex(random_bytes(32));
    echo \View::make('sGallery::settings', [
        'settings' => \Seiger\sGallery\Services\GallerySettings::read(),
        'resourceTemplates' => \EvolutionCMS\Models\SiteTemplate::orderBy('templatename')->get(),
        'token' => $_SESSION['sgallery_settings_token'],
    ])->render();
});
