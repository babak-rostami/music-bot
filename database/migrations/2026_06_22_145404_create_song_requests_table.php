<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول درخواست آهنگ‌هایی که کاربر جستجو کرده ولی پیدا نشده
     * ادمین بعداً این درخواست‌ها رو بررسی می‌کنه و آهنگ رو آپلود می‌کنه
     */
    public function up(): void
    {
        Schema::create('song_requests', function (Blueprint $table) {
            $table->id();

            // اطلاعات کاربر تلگرام
            $table->unsignedBigInteger('user_id')->index();
            $table->string('username', 64)->nullable();
            $table->string('first_name', 128)->nullable();
            $table->string('last_name', 128)->nullable();
            $table->unsignedBigInteger('chat_id');

            // متن جستجو
            $table->text('query');

            // وضعیت: pending (در انتظار) | processing (در حال آپلود) | completed (آپلود شد) | rejected (رد شد)
            $table->string('status', 32)->default('pending')->index();

            // یادداشت ادمین (اختیاری)
            $table->text('admin_note')->nullable();

            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_requests');
    }
};
