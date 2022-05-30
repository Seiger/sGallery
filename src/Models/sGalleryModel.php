<?php namespace Seiger\sGallery\Models;

use EvolutionCMS\Facades\UrlProcessor;
use Illuminate\Database\Eloquent;
use Illuminate\Support\Facades\DB;

class sGalleryModel extends Eloquent\Model
{
    const UPLOAD = MODX_BASE_PATH . "assets/sgallery/";
    const UPLOADED = MODX_SITE_URL . "assets/sgallery/";

    const TYPE_IMAGE = "image";
    const TYPE_VIDEO = "video";
    const TYPE_YOUTUBE = "youtube";

    const VIEW_TAB = "tab";
    const VIEW_SECTION = "section";

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
        return $this->leftJoin('s_gallery_fields', function ($leftJoin) use ($locale) {
            $leftJoin->on('s_galleries.id', '=', 's_gallery_fields.key')
                ->where('lang', function ($leftJoin) use ($locale) {
                    $leftJoin->select('lang')
                        ->from('s_gallery_fields')
                        ->whereRaw(DB::getTablePrefix().'s_gallery_fields.key = '.DB::getTablePrefix().'s_galleries.id')
                        ->whereIn('lang', [$locale, 'base'])
                        ->orderByRaw('FIELD(lang, "'.$locale.'", "base")')
                        ->limit(1);
                });
        });
    }

    /**
     * Get the image src link
     *
     * @return string
     */
    public function getFileSrcAttribute()
    {
        if (!empty($this->file) && is_file(self::UPLOAD.$this->resource_type.'/'.$this->parent.'/'.$this->file)) {
            $imageSrc = self::UPLOADED.$this->resource_type.'/'.$this->parent.'/'.$this->file;
        } else {
            $imageSrc = self::UPLOADED.'noimage.png';
        }

        return $imageSrc;
    }
}