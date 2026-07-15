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
        Schema::create('song_download_links', function (Blueprint $table) {
            $table->id();

            $table->foreignId('song_id')
                ->constrained('songs')
                ->cascadeOnDelete();

            $table->string('provider')->index(); // مثلا mybia2music.com
            $table->string('quality')->nullable(); // 128 یا 320
            $table->string('download_url');

            $table->timestamps();

            $table->unique(['provider', 'download_url'], 'song_download_links_provider_url_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('song_download_links');
    }
};
