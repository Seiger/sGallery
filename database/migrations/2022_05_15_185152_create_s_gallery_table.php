<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Seiger\sGallery\Services\GalleryMigration;

/** Create missing tables without replacing an existing Evolution installation. */
class CreateSGalleryTable extends Migration
{
    /**
     * Create each missing table independently, including recovery from a partial installation.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('s_galleries')) {
            Schema::create('s_galleries', function (Blueprint $table) {
                $table->id();
                $table->integer('parent')->index();
                $table->integer('position')->default(0);
                $table->string('file', 256);
                $table->string('type', 10)->default('image');
                $table->string('resource_type', 64)->default('resource');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('s_gallery_fields')) {
            Schema::create('s_gallery_fields', function (Blueprint $table) {
                $table->id();
                $table->integer('key')->index();
                $table->string('lang', 4)->default('base');
                $table->string('alt', 100)->default('');
                $table->string('title', 100)->default('');
                $table->string('description', 1024)->default('');
                $table->string('link_text', 100)->default('');
                $table->string('link', 1024)->default('');
                $table->timestamps();
            });
        }
    }

    /**
     * Remove application-created tables while retaining the installer's baseline.
     *
     * @return void
     */
    public function down()
    {
        if (GalleryMigration::isInstallerBaseline('2022_05_15_185152_create_s_gallery_table')) {
            return;
        }
        Schema::dropIfExists('s_gallery_fields');
        Schema::dropIfExists('s_galleries');
    }
}
