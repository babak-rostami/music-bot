<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->string('youtube_link')->nullable()->after('aparat_link');
            $table->string('spotify_link')->nullable()->after('youtube_link');
            $table->string('soundcloud_link')->nullable()->after('spotify_link');
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn(['youtube_link', 'spotify_link', 'soundcloud_link']);
        });
    }
};
