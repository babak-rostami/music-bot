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
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('artist_name');
            $table->string('source_url')->unique();
            $table->text('lyrics')->nullable();
            $table->bigInteger('downloads_count')->default(0);
            $table->string('telegram_file_id_128')->nullable();
            $table->string('telegram_file_id_320')->nullable();
            $table->string('download_link_128')->nullable();
            $table->string('download_link_320')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
