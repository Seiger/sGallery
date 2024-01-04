<?php
Event::listen('evolution.OnDocFormRender', function($params) {
    // TODO Refactor after some times
    if (file_exists(EVO_CORE_PATH . 'custom/config/cms/settings/sGallery.php')) {
        copy(EVO_CORE_PATH . 'custom/config/cms/settings/sGallery.php', EVO_CORE_PATH . 'custom/config/seiger/settings/sGallery.php');
        unlink(EVO_CORE_PATH . 'custom/config/cms/settings/sGallery.php');
    }

    if (in_array($params['template'], config('seiger.settings.sGallery', [0])) && $params['id'] > 0) {
        return sGallery::initialise();
    }
});