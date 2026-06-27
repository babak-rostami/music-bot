<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature = 'make:admin {username?} {email?} {password?}';

    protected $description = 'یک کاربر ادمین جدید ایجاد می‌کند';

    public function handle(): int
    {
        $username = $this->argument('username')
            ?? $this->ask('نام کاربری را وارد کنید');

        $email = $this->argument('email')
            ?? $this->ask('ایمیل را وارد کنید');

        $password = $this->argument('password')
            ?? $this->secret('رمز عبور را وارد کنید');

        $name = $this->ask('نام کامل (اختیاری)', null);
        $phone    = $this->ask('شماره تلفن (اختیاری)', null);

        // بررسی تکراری نبودن
        if (User::where('username', $username)->exists()) {
            $this->error("نام کاربری «{$username}» قبلاً ثبت شده است!");
            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error("ایمیل «{$email}» قبلاً ثبت شده است!");
            return self::FAILURE;
        }

        $user = User::create([
            'username'   => $username,
            'name'       => $name ?? $username,
            'email'      => $email,
            'phone'      => $phone,
            'password'   => Hash::make($password),
            'role'       => 'admin',
            'is_active'  => true,
        ]);

        $this->info("✅ ادمین «{$user->username}» با موفقیت ایجاد شد!");
        return self::SUCCESS;
    }
}
