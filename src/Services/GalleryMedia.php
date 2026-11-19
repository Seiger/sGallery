<?php namespace Seiger\sGallery\Services;

use RuntimeException;
use Seiger\sGallery\Models\sGalleryModel;
use Symfony\Component\Mime\MimeTypes;

/**
 * Classify supported uploads from server MIME and assign a gallery media type.
 *
 * @since 1.8.0
 */
final class GalleryMedia
{
    private const EXTENSIONS = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'tif', 'tiff', 'heic', 'heif', 'jxl'],
        'video' => ['mp4', 'm4v', 'mov', 'webm', 'ogv', 'mpeg', 'mpg'],
        'audio' => ['mp3', 'm4a', 'wav', 'ogg', 'oga', 'flac', 'aac'],
        'pdf' => ['pdf'],
        'download' => ['odp', 'ods', 'odt', 'ppt', 'pptx', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'zip'],
    ];

    /**
     * Accept formats allowed by both the package and the relevant CMS upload settings.
     * The client suffix identifies a supported format but is never used without MIME agreement.
     *
     * @return array{type: string, extension: string}
     * @since 1.8.0
     */
    public static function classify(string $path, string $suffix, string $group): array
    {
        $suffix = strtolower($suffix);
        $type = null;
        foreach (self::EXTENSIONS as $mediaType => $extensions) {
            if (in_array($suffix, $extensions, true)) {
                $type = $mediaType;
                break;
            }
        }

        $keys = match ($group) {
            'media' => ['upload_images', 'upload_media'],
            'download' => ['upload_files'],
            default => ['upload_images', 'upload_media', 'upload_files'],
        };
        $defaults = ['upload_images' => 'png,jpg,jpeg,mp4', 'upload_media' => 'mp3,mp4',
            'upload_files' => 'odp,odt,pdf,ppt,pptx,doc,docx'];
        $allowed = [];
        foreach ($keys as $key) {
            $allowed = array_merge($allowed, preg_split('/\s*,\s*/', strtolower(trim((string) evo()->getConfig($key, $defaults[$key])))));
        }

        $documents = in_array($type, ['pdf', 'download'], true);
        $mime = MimeTypes::getDefault()->guessMimeType($path);
        $aliases = ['jpeg' => 'jpg', 'tiff' => 'tif'];
        $extension = $aliases[$suffix] ?? $suffix;
        $configured = in_array($extension, array_map(static fn ($ext) => $aliases[$ext] ?? $ext, $allowed), true);
        if ($type === null || !$configured || ($group === 'download' && !$documents) ||
            ($group === 'media' && $documents) || !in_array($mime, MimeTypes::getDefault()->getMimeTypes($suffix), true)) {
            throw new RuntimeException('Unsupported gallery file type.');
        }
        if ($type === 'image' && $extension === 'jxl' && NativeImage::imagickInputFormat($path) !== 'JXL') {
            throw new RuntimeException('Invalid gallery image.');
        }
        if ($type === 'image' && !in_array($extension, ['heic', 'heif', 'jxl'], true) && !@getimagesize($path)) {
            throw new RuntimeException('Invalid gallery image.');
        }

        return ['type' => $type, 'extension' => $extension];
    }

    /** Get the fixed preview for a media type, including a fallback for legacy downloads. @since 1.8.0 */
    public static function preview(string $type): string
    {
        return match ($type) {
            sGalleryModel::TYPE_IMAGE => 'partials.image',
            sGalleryModel::TYPE_VIDEO => 'partials.video',
            sGalleryModel::TYPE_YOUTUBE => 'partials.youtube',
            sGalleryModel::TYPE_PDF => 'partials.pdf',
            default => 'partials.download',
        };
    }

    /** Accept local links and HTTP(S) URLs suitable for a quoted HTML attribute. @since 1.8.0 */
    public static function link(string $value): bool
    {
        if (str_contains($value, '&#')) {
            return false;
        }
        return self::validUrl($value) && self::validUrl(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** Check URL syntax before and after HTML entity interpretation. @since 1.8.0 */
    private static function validUrl(string $value): bool
    {
        if ($value === '' || strpbrk($value, "\\<>\"'") !== false || preg_match('~[\x00-\x20\x7F]~', $value) ||
            str_starts_with($value, '//')) {
            return false;
        }
        $parts = parse_url($value);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        return !isset($parts['scheme']) ||
            (in_array(strtolower($parts['scheme']), ['http', 'https'], true) && !empty($parts['host']));
    }
}
