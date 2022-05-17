# sGallery for Evolution CMS 3

**sGallery** Plugin for attaching Images and Video clips (YouTube) to a resource in the Evolution CMS admin panel.

## Install by artisan package installer:

Run in you /core/ folder:

``php artisan package:installrequire seiger/sgallery "*"``

Create config file in **core/custom/config/cms/settings** with 
name **sgallery.php** the file should return a 
comma-separated list of templates.

``php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"``

``php artisan vendor:publish --provider="Intervention\Image\ImageServiceProviderLaravelRecent"``

Run make DB structure with command:

``php artisan migrate``

For correct processing of cached images, you need to comment out the line in .htaccess

``#RewriteRule \.(jpg|jpeg|png|gif|ico)$ - [L]``