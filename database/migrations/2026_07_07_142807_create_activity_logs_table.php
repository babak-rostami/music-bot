<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول لاگ همه اتفاقات ربات
 *
 * هر اتفاقی که می‌افتد (پیام، ارسال آهنگ، خطا، عضویت و...) در این جدول ثبت می‌شود.
 * علاوه بر ارسال نوتیفیکیشن به ادمین، تاریخچه کامل رویدادها هم در دیتابیس نگه‌داری می‌شود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // ── نوع و سطح رویداد ──
            $table->string('type');                   // message_received, song_sent, song_send_failed, error, user_joined, user_blocked, ...
            $table->string('level')->default('info'); // info, success, warning, error
            $table->string('platform')->nullable();    // telegram, bale

            // ── کاربر مرتبط ──
            $table->bigInteger('user_id')->nullable();
            $table->string('chat_id')->nullable();

            // ── محتوای رویداد ──
            $table->text('message')->nullable();      // توضیحات قابل‌خواندن
            $table->json('data')->nullable();          // داده‌های اضافی (JSON)

            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index('level');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
