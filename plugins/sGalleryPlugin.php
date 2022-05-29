<?php

Event::listen('evolution.OnDocFormRender', function($params) {
    if (in_array($params['template'], evo()->getConfig('sgallery', [0])) && $params['id'] > 0) {
        return sGallery::initialise();
    }
});