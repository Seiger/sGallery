# sGallery for Evolution CMS 3

## Install by artisan package installer:

Run in you /core/ folder:

``php artisan package:installrequire seiger/sgallery "*"``

Create config file in **core/custom/config/cms/settings** with 
name **sGalleryTemplateConfig.php** the file should return a 
comma-separated list of templates.

``echo '<?php return 1;' > custom/config/cms/settings/sGalleryTemplateConfig.php``