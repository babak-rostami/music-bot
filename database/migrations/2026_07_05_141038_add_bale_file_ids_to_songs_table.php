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
            // ستون‌های file_id مخصوص بله (برای کش فایل‌ها)
            $table->string('bale_file_id_128')->nullable()->after('telegram_file_id_320');
            $table->string('bale_file_id_320')->nullable()->after('bale_file_id_128');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn(['bale_file_id_128', 'bale_file_id_320']);
        });
    }
};
