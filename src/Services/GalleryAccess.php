<?php namespace Seiger\sGallery\Services;

use EvolutionCMS\Models\SiteContent;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Authorize manager actions against the parent and gallery block.
 *
 * @since 1.8.0
 */
final class GalleryAccess
{
    /** Abort a gallery operation with a status the manager route can return. @since 1.8.0 */
    public static function require(bool $condition, int $status): void
    {
        if (!$condition) {
            throw new HttpException($status);
        }
    }

    /**
     * Apply Evo's resource role and document-group checks. Integrations with other
     * parent types can supply a server-side policy with their own resource checks.
     * @since 1.8.0
     */
    public static function authorize(string $action, string $type, int $parent, string $block): void
    {
        $cms = evo();
        $role = (int) ($_SESSION['mgrRole'] ?? 0);
        self::require($role > 0 && (int) $cms->getLoginUserID('mgr') > 0 && $cms->hasPermission('access_permissions', 'mgr'), 403);

        $settings = (array) config('seiger.settings.sGallery', []);
        $policy = $settings['itemPolicies'][$type] ?? null;
        if ($type !== 'resource') {
            // Preserve administrator access to existing custom module integrations.
            // A configured policy also applies to administrators.
            self::require($policy !== null ? is_callable($policy) && $policy($action, $parent, $block, $cms) === true : $role === 1, 403);
            return;
        }

        $allowed = $action === 'read'
            ? $cms->hasPermission('view_document', 'mgr') || $cms->hasPermission('edit_document', 'mgr')
            : $cms->hasPermission('edit_document', 'mgr') && $cms->hasPermission('save_document', 'mgr');
        self::require($allowed, 403);
        if ($action === 'delete') {
            self::require($cms->hasPermission('delete_document', 'mgr'), 403);
        }

        $query = SiteContent::query()->where('site_content.id', $parent)->where('site_content.deleted', 0);
        if ($role !== 1) {
            $groups = (array) ($_SESSION['mgrDocgroups'] ?? []);
            $query->where(function ($query) use ($groups) {
                $query->where('site_content.privatemgr', 0)->orWhereExists(function ($query) use ($groups) {
                    $query->selectRaw('1')->from('document_groups')
                        ->whereColumn('document_groups.document', 'site_content.id')
                        ->whereIn('document_groups.document_group', $groups);
                });
            });
        }
        $template = $query->value('site_content.template');
        self::require($template !== null, 403);
        $blocks = GallerySettings::assignments($settings)[(int) $template] ?? [];
        if (!in_array($block, $blocks ?: ['1'], true)) {
            self::require(is_callable($policy) && $policy($action, $parent, $block, $cms) === true, 403);
        }
    }

    /** Apply the file browser's distinction between image and general file permissions. @since 1.8.0 */
    public static function authorizeLibrary(?string $type = null): void
    {
        $cms = evo();
        self::require($cms->hasPermission('file_manager', 'mgr') ||
            (($type === null || $type === 'image') && $cms->hasPermission('assets_images', 'mgr')) ||
            ($type !== 'image' && $cms->hasPermission('assets_files', 'mgr')), 403);
    }
}
