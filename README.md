# sGallery for Evolution CMS 3
![sGallery](https://user-images.githubusercontent.com/12029039/169609394-08ea36d6-2393-4261-aff2-348f73a6103c.png)
[![Latest Stable Version](https://img.shields.io/packagist/v/seiger/sgallery?label=version)](https://packagist.org/packages/seiger/sgallery)
[![CMS Evolution](https://img.shields.io/badge/CMS-Evolution-brightgreen.svg)](https://github.com/evolution-cms/evolution)
![PHP version](https://img.shields.io/badge/PHP->=v7.4-red.svg?php=7.4)
[![License](https://img.shields.io/packagist/l/seiger/sgallery)](https://packagist.org/packages/seiger/sgallery)
[![Total Downloads](https://img.shields.io/packagist/dt/seiger/sgallery?color=blue)](https://packagist.org/packages/seiger/sgallery)

**sGallery** Plugin for attaching Images and Video clips (YouTube) to a resource in the Evolution CMS admin panel.

## Features

- [x] Upload Images.
- [x] Upload Videos.
- [x] Include Youtube.
- [x] Sort positions.
- [x] Text fields for file.
- [x] Resize and WEBP convert image.

## Install by artisan package installer

Run in you /core/ folder:

``php artisan package:installrequire seiger/sgallery "*"``

Create config file in **core/custom/config/cms/settings** with 
name **sgallery.php** the file should return a 
comma-separated list of templates.

``php artisan vendor:publish --provider="Seiger\sGallery\sGalleryServiceProvider"``

Run make DB structure with command:

``php artisan migrate``

## Configure

Templates for displaying gallery tabs are configured in the 

``core/custom/config/cms/settings/sGallery.php``

file, where the array contains template IDs for connecting the gallery.