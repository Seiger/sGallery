<?php namespace Seiger\sGallery\Models;

use EvolutionCMS\Facades\UrlProcessor;
use Illuminate\Database\Eloquent;

class sGalleryModel extends Eloquent\Model
{
    const UPLOAD = MODX_BASE_PATH . "assets/images/sgallery/";
    const UPLOADED = MODX_SITE_URL . "assets/images/sgallery/";

    const TYPE_IMAGE = "image";
    const TYPE_VIDEO = "video";
    const TYPE_YOUTUBE = "youtube";

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 's_galleries';

    /**
     * Get the file item fields with lang
     *
     * @param $query
     * @param $locale
     * @return mixed
     */
    public function scopeLang($query, $locale)
    {
        return $this->leftJoin('s_gallery_fields', 's_galleries.id', '=', 's_gallery_fields.key')->where('lang', '=', $locale);
    }

    /**
     * Get the image src link
     *
     * @return string
     */
    public function getFileSrcAttribute()
    {
        if (!empty($this->file) && is_file(self::UPLOAD . $this->parent . '/' . $this->file)) {
            $imageSrc = self::UPLOADED . $this->parent . '/' . $this->file;
        } else {
            $imageSrc = self::UPLOADED . 'noimage.png';
        }

        return $imageSrc;
    }
}