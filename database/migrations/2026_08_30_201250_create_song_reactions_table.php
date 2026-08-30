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
        Schema::create('song_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('song_id')->constrained('songs')->cascadeOnDelete();

            // session (سایت) | telegram | bale
            $table->string('identity_type', 20);
            // session_id سایت یا user_id پلتفرم، به‌صورت رشته
            $table->string('identity_id', 64);

            $table->enum('type', ['like', 'dislike']);

            $table->timestamps();

            $table->unique(['song_id', 'identity_type', 'identity_id'], 'song_reactions_unique');
            $table->index(['song_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('song_reactions');
    }
};
