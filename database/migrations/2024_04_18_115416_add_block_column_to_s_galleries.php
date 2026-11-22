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
        if (!Schema::hasTable('s_galleries') || Schema::hasColumn('s_galleries', 'block')) {
            return;
        }

        Schema::table('s_galleries', function (Blueprint $table) {
            $table->string('block', 64)->default('1')->after('parent');
        });
    }

    /**
     * Reverse application changes without removing an installer-owned block column.
     */
    public function down(): void
    {
        if (GalleryMigration::isInstallerBaseline('2024_04_18_115416_add_block_column_to_s_galleries')) {
            return;
        }
        if (!Schema::hasTable('s_galleries') || !Schema::hasColumn('s_galleries', 'block')) {
            return;
        }

        Schema::table('s_galleries', function (Blueprint $table) {
            $table->dropColumn('block');
        });
    }
};
