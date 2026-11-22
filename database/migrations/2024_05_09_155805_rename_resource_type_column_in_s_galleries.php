<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Seiger\sGallery\Services\GalleryMigration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (
            !Schema::hasTable('s_galleries') ||
            !Schema::hasColumn('s_galleries', 'resource_type') ||
            Schema::hasColumn('s_galleries', 'item_type')
        ) {
            return;
        }

        Schema::table('s_galleries', function (Blueprint $table) {
            $table->renameColumn('resource_type', 'item_type');
        });
    }

    /**
     * Reverse application changes without reverting the installer's current schema.
     */
    public function down(): void
    {
        if (GalleryMigration::isInstallerBaseline('2024_05_09_155805_rename_resource_type_column_in_s_galleries')) {
            return;
        }
        if (
            !Schema::hasTable('s_galleries') ||
            !Schema::hasColumn('s_galleries', 'item_type') ||
            Schema::hasColumn('s_galleries', 'resource_type')
        ) {
            return;
        }

        Schema::table('s_galleries', function (Blueprint $table) {
            $table->renameColumn('item_type', 'resource_type');
        });
    }
};
