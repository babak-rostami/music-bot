<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('music_finder_requests', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);              // telegram | bale
            $table->unsignedBigInteger('user_id');        // chat_id کاربر
            $table->string('source', 20);                 // database | external_service
            $table->foreignId('song_id')->nullable()->constrained('songs')->nullOnDelete();
            $table->string('query_text')->nullable();     // عنوان/متن، فقط برای external_service
            $table->timestamps();

            $table->index(['platform', 'user_id', 'created_at']);
            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('music_finder_requests');
    }
};
