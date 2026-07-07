<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول کاربران ربات‌ها (تلگرام + بله)
 *
 * ⚠️ نکته مهم طراحی:
 * تلگرام و بله دو سیستم کاملاً مستقل هستند و آیدی کاربرانشان با هم
 * هیچ ارتباطی ندارد. بنابراین هر ردیف نشان‌دهنده عضویت یک کاربر در
 * «یک» پلتفرم است. کلید یکتا = (platform, user_id).
 *
 * اگر کسی هم در ربات تلگرام و هم ربات بله عضو باشد → دو ردیف جداگانه ساخته می‌شود.
 *
 * با این جدول می‌توانید:
 *  - ببینید هر کاربر در کدام پلتفرم فعال است
 *  - تعداد اعضای هر ربات را بشمارید
 *  - کاربرانی که ربات را بلاک کرده‌اند را شناسایی کنید (از طریق شمارش خطای ارسال)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_users', function (Blueprint $table) {
            $table->id();

            // ── پلتفرم + شناسه کاربر در آن پلتفرم ──
            $table->string('platform');           // telegram | bale
            $table->bigInteger('user_id');        // شناسه کاربر داخل همان پلتفرم

            // ── اطلاعات کاربر ──
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            // ── ردیابی بلاک شدن ──
            // هر بار ارسال ناموفق → یکی اضافه می‌شود
            // وقتی از حد مشخصی (پیش‌فرض ۳) عبور کرد → is_blocked = true
            $table->unsignedInteger('failed_send_count')->default(0);
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('blocked_at')->nullable();

            // ── فعالیت ──
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamps();

            // کلید یکتا: هر کاربر فقط یکبار در هر پلتفرم ثبت می‌شود
            $table->unique(['platform', 'user_id']);

            // ایندکس برای کوئری‌های پرکاربرد
            $table->index('platform');
            $table->index('is_blocked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_users');
    }
};
