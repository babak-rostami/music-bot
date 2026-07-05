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
        Schema::table('songs', function (Blueprint $table) {
            $table->string('cover_image')->nullable();
            $table->string('aparat_link')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->text('description')->nullable();

            $table->dropIndex(['created_at']);
            $table->index(['created_at', 'views_count'], 'songs_trending_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropIndex('songs_trending_index');

            $table->dropColumn([
                'cover_image',
                'aparat_link',
                'views_count',
                'description',
            ]);

            $table->index('created_at');
        });
    }
};
