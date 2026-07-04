<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('s_galleries') || !Schema::hasColumn('s_galleries', 'block')) {
            return;
        }

        Schema::table('s_galleries', function (Blueprint $table) {
            $table->dropColumn('block');
        });
    }
};
