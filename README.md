# sGallery for Evolution CMS 3

**sGallery** Plugin for attaching Images and Video clips (YouTube) to a resource in the Evolution CMS admin panel.

## Install by artisan package installer:

Run in you /core/ folder:

``php artisan package:installrequire seiger/sgallery "*"``

Create config file in **core/custom/config/cms/settings** with 
name **sGalleryTemplateConfig.php** the file should return a 
comma-separated list of templates.

``echo '<?php return 1;' > custom/config/cms/settings/sGalleryTemplateConfig.php``

Run make DB structure with command:

``php artisan migrate``
