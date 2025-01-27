<?php namespace Seiger\sGallery\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Seiger\sGallery\sGallery;

/**
 * Class sGalleryModel
 *
 * This class represents a gallery model for managing gallery data in the application.
 *
 * @property int $id
 * @property string $item_type
 * @property string $parent
 * @property string $file
 * @property string $type
 *
 * @property-read string $path
 * @property-read string $src
 *
 * @method static Builder|sGalleryModel lang(string $locale)
 * @method static Builder|sGalleryModel newModelQuery()
 * @method static Builder|sGalleryModel newQuery()
 * @method static Builder|sGalleryModel query()
 */
class sGalleryModel extends Model
{
    const UPLOAD = EVO_BASE_PATH . "assets/sgallery/";
    const UPLOADED = EVO_SITE_URL . "assets/sgallery/";
    const NOIMAGE = EVO_SITE_URL . "assets/site/noimage.png";
    const CACHE_DIR = "assets/cache/";

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
     * The attributes that should be appended to the model's array form.
     *
     * @var array
     */
    protected $appends = ['src', 'path'];

    private $cachedFilePath;

    /**
     * Get the file item fields with lang
     *
     * @param $query
     * @param $locale
     * @return mixed
     */
    public function scopeLang($query, $locale)
    {
        return $this->select('*')->leftJoin('s_gallery_fields', function ($leftJoin) use ($locale) {
            $leftJoin->on('s_galleries.id', '=', 's_gallery_fields.key')
                ->where('lang', function ($leftJoin) use ($locale) {
                    $leftJoin->select('lang')
                        ->from('s_gallery_fields')
                        ->whereRaw(DB::getTablePrefix().'s_gallery_fields.key = '.DB::getTablePrefix().'s_galleries.id')
                        ->whereIn('lang', [$locale, 'base'])
                        ->orderByRaw('FIELD(lang, "'.$locale.'", "base")')
                        ->limit(1);
                });
        })->addSelect(DB::Raw('(CASE WHEN `type` = \'image\' THEN CONCAT("/assets/sgallery/", item_type, "/", parent, "/", file) ELSE "" END) as src'));
    }

    /**
     * Retrieves the source attribute for an image.
     *
     * If the file property is not empty and a valid file exists at the specified path, the source attribute will be set to
     * the path to the file. Otherwise, the source attribute will be set to the path to the default image (noimage.png).
     *
     * @return string The source attribute value for the image.
     */
    protected function getSrcAttribute(): string
    {
        switch ($this->type) {
            case self::TYPE_IMAGE:
                $src = self::NOIMAGE;
                if (!empty($this->file) && is_file(self::UPLOAD . $this->item_type . '/' . $this->parent . '/' . $this->file)) {
                    $src = self::UPLOADED . $this->item_type . '/' . $this->parent . '/' . $this->file;
                } elseif (!empty($this->file) && is_file(EVO_BASE_PATH . $this->file)) {
                    $src = EVO_SITE_URL . $this->file;
                } elseif (!empty($this->file) && sGallery::hasLink($this->file)) {
                    $src = $this->file;
                }
                break;
            case self::TYPE_VIDEO:
                $src = self::UPLOADED . 'video_placeholder.png';
                break;
            case self::TYPE_PDF:
                $src = self::UPLOADED . 'pdf_icon.png';
                break;
            default:
                $src = self::NOIMAGE;
                break;
        }
        return $src;
    }

    /**
     * Get the full path to the file on the server.
     *
     * This method constructs the full file path on the server for the gallery item.
     * If the file exists, it returns the full path; otherwise, it returns the path to a default "noimage.png" file.
     *
     * @return string The full path to the file on the server.
     */
    public function getPathAttribute(): string
    {
        if ($this->cachedFilePath) {
            return $this->cachedFilePath;
        }

        if (!empty($this->file) && is_file(self::UPLOAD . $this->item_type . '/' . $this->parent . '/' . $this->file)) {
            $this->cachedFilePath = self::UPLOAD . $this->item_type . '/' . $this->parent . '/' . $this->file;
        } elseif (!empty($this->file) && is_file(EVO_BASE_PATH . $this->file)) {
            $this->cachedFilePath = EVO_BASE_PATH . $this->file;
        } elseif (!empty($this->file) && sGallery::hasLink($this->file)) {
            $this->cachedFilePath = $this->file;
        }

        return $this->cachedFilePath ?? self::UPLOAD . 'noimage.png';
    }
}
