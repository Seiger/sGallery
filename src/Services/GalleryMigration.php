<?php namespace Seiger\sGallery\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Preserve the Evolution installer baseline during application migration rollback. @since 1.9.0 */
final class GalleryMigration
{
    /** Check the installer's separate history before undoing an adopted migration. @since 1.9.0 */
    public static function isInstallerBaseline(string $migration): bool
    {
        return Schema::hasTable('migrations_install') &&
            DB::table('migrations_install')->where('migration', $migration)->exists();
    }
}
