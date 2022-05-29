<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSGalleryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('s_galleries', function (Blueprint $table) {
            $table->id();
            $table->integer('parent')->index();
            $table->integer('position')->default(0);
            $table->string('file', 100);
            $table->string('type', 10)->default('image');
            $table->string('resource', 64)->default('resource');
            $table->timestamps();
        });

        Schema::create('s_gallery_fields', function (Blueprint $table) {
            $table->id();
            $table->integer('key')->index();
            $table->string('lang', 4)->default('base');
            $table->string('alt', 100)->default('');
            $table->string('title', 100)->default('');
            $table->string('description', 100)->default('');
            $table->string('link_text', 100)->default('');
            $table->string('link', 100)->default('');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('s_gallery_fields');
        Schema::dropIfExists('s_galleries');
    }
}
