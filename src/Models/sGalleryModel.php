<?php namespace Seiger\sGallery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Class sGalleryModel
 *
 * This class represents a gallery model for managing gallery data in the application.
 * It extends the base Model class.
 */
class sGalleryModel extends Model
{
    const UPLOAD = MODX_BASE_PATH . "assets/sgallery/";
    const UPLOADED = MODX_SITE_URL . "assets/sgallery/";

    const TYPE_IMAGE = "image";
    const TYPE_VIDEO = "video";
    const TYPE_YOUTUBE = "youtube";
    const TYPE_PDF = "pdf";

    const VIEW_TAB = "tab";
    const VIEW_SECTION = "section";
    const VIEW_SECTION_DOWNLOADS = "sectionDownloads";

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 's_galleries';

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['src'];

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
     * Retrieves the source attribute for an image.
     *
     * If the file property is not empty and a valid file exists at the specified path, the source attribute will be set to
     * the path to the file. Otherwise, the source attribute will be set to the path to the default image (noimage.png).
     *
     * @return string The source attribute value for the image.
     */
    protected function getSrcAttribute()
    {
        if (!empty($this->file) && is_file(self::UPLOAD.$this->resource_type.'/'.$this->parent.'/'.$this->file)) {
            $src = self::UPLOADED.$this->resource_type.'/'.$this->parent.'/'.$this->file;
        } else {
            $src = self::UPLOADED.'noimage.png';
        }

        return $src;
    }
}