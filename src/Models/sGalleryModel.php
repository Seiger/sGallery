<?php namespace Seiger\sGallery\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Seiger\sGallery\Services\GalleryFiles;
use Seiger\sGallery\Services\GalleryMedia;
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
     * Get the project-relative directory for generated image derivatives.
     *
     * @return string
     *
     * @since 1.5.1
     */
    public static function imageCacheDir(): string
    {
        $directory = config('seiger.settings.sGallery.imageCacheDir', self::CACHE_DIR);

        if (!is_string($directory) || trim($directory) === '') {
            return self::CACHE_DIR;
        }

        return trim(str_replace('\\', '/', $directory), '/') . '/';
    }

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

    /**
     * The attributes that may be mass assigned when a gallery row is persisted.
     *
     * @var array<int, string>
     */
    protected $fillable = ['parent', 'block', 'position', 'file', 'type', 'item_type'];


    /**
     * Get the file item fields with lang
     *
     * @param $query
     * @param $locale
     * @return mixed
     */
    public function scopeLang($query, $locale)
    {
        $grammar = $query->getQuery()->getGrammar();
        $quotedLocale = DB::connection()->getPdo()->quote($locale);
        $quotedBase = DB::connection()->getPdo()->quote('base');
        $langColumn = $grammar->wrap('lang');
        $langOrderSql = 'CASE WHEN ' . $langColumn . ' = ' . $quotedLocale
            . ' THEN 0 WHEN ' . $langColumn . ' = ' . $quotedBase
            . ' THEN 1 ELSE 2 END';
        return $query->select('s_galleries.*', 's_gallery_fields.lang', 's_gallery_fields.alt',
            's_gallery_fields.title', 's_gallery_fields.description', 's_gallery_fields.link_text', 's_gallery_fields.link')
            ->leftJoin('s_gallery_fields', function ($leftJoin) use ($locale, $langOrderSql) {
            $leftJoin->on('s_galleries.id', '=', 's_gallery_fields.key')
                ->where('lang', function ($leftJoin) use ($locale, $langOrderSql) {
                    $leftJoin->select('lang')
                        ->from('s_gallery_fields as sgf')
                        ->whereColumn('sgf.key', 's_galleries.id')
                        ->whereIn('lang', [$locale, 'base'])
                        ->orderByRaw($langOrderSql)
                        ->limit(1);
                });
        });
    }

    /** Return the image source or media placeholder for existing frontend templates. */
    protected function getSrcAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_IMAGE => $this->mediaUrl() ?? self::NOIMAGE,
            self::TYPE_VIDEO => self::UPLOADED . 'video_placeholder.png',
            self::TYPE_PDF => self::UPLOADED . 'pdf_icon.png',
            default => self::NOIMAGE,
        };
    }

    /** Omit invalid legacy links from gallery templates while leaving the stored value intact. @since 1.8.0 */
    protected function getLinkAttribute(?string $value): string
    {
        return $value !== null && GalleryMedia::link($value) ? $value : '';
    }

    /** Resolve the stored media for rendering, with a placeholder for missing or unsafe paths. */
    public function getPathAttribute(): string
    {
        $relative = $this->localMedia();
        if ($relative !== null) {
            return GalleryFiles::local($relative) ?? EVO_BASE_PATH . 'assets/site/noimage.png';
        }

        return $this->remoteImage() ?? EVO_BASE_PATH . 'assets/site/noimage.png';
    }

    /** Expose only a resolved media URL to Blade previews. @since 1.8.0 */
    public function mediaUrl(): ?string
    {
        $relative = $this->localMedia();
        return $relative !== null ? GalleryFiles::url($relative) : $this->remoteImage();
    }

    /** Distinguish owned filenames from shared library paths. @since 1.8.0 */
    private function localMedia(): ?string
    {
        if ($this->type === self::TYPE_YOUTUBE) {
            return null;
        }

        return GalleryFiles::owned($this) ?? GalleryFiles::library((string) $this->file);
    }

    /** Keep existing external image URLs usable as browser sources. @since 1.8.0 */
    private function remoteImage(): ?string
    {
        $file = (string) $this->file;
        return $this->type === self::TYPE_IMAGE && sGallery::hasLink($file) && GalleryMedia::link($file) ? $file : null;
    }
}
