<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->string('cover_path')->nullable();
            $table->unique(['title', 'artist_name'], 'songs_title_artist_unique');
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn(['cover_path']);
            $table->dropUnique('songs_title_artist_unique');
        });
    }
};
