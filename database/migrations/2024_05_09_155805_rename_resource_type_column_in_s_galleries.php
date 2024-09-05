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
        Schema::table('s_galleries', function (Blueprint $table) {
            $table->renameColumn('resource_type', 'item_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('s_galleries', function (Blueprint $table) {
            $table->renameColumn('item_type', 'resource_type');
        });
    }
};
