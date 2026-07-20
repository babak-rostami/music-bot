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
        Schema::table('song_download_links', function (Blueprint $table) {
            $table->unsignedInteger('priority')->default(0)->after('download_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('song_download_links', function (Blueprint $table) {
            $table->dropColumn([
                'priority'
            ]);
        });
    }
};
