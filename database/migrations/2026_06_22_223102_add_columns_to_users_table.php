<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique();
            $table->string('phone')->unique()->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->enum('role', ['admin', 'user'])->default('user');
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'full_name',
                'phone',
                'bio',
                'avatar',
                'role',
                'is_active',
            ]);
        });
    }
};
