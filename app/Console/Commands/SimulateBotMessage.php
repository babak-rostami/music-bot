<?php

namespace App\Console\Commands;

use App\Bot\Bale\Services\BaleActionService;
use App\Bot\Telegram\Services\TelegramActionService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * دستور تست بات‌ها بدون نیاز به وب‌هوک واقعی یا سرور جدا.
 *
 * این دستور یه پیام/کلیک قلابی (fake) دقیقاً به همون شکلی که تلگرام/بله
 * واقعاً می‌فرستن می‌سازه، و مستقیم به همون ActionServiceای می‌ده که
 * وب‌هوک واقعی هم بهش می‌ده (TelegramActionService/BaleActionService) —
 * یعنی کل مسیر واقعی کد (چک عضویت، جستجوی پیشرفته، فلوی ادمین، صف Jobها)
 * دقیقاً همونیه که تو پروداکشن اجرا می‌شه، فقط بدون این‌که واقعاً از
 * تلگرام/بله پیام اومده باشه.
 *
 * نتیجه‌ی نهایی (پیام‌ها/آهنگ‌ها) مستقیم به چت خود ادمین (همون شناسه‌ای
 * که توی TELEGRAM_ADMIN_ID / BALE_ADMIN_ID گذاشتی) فرستاده می‌شه —
 * پس بازش کن توی تلگرام/بله خودت و جواب رو همونجا ببین.
 *
 * ⚠️ نیازمندی: کانتینرهای app و صف‌ها (queue-download, queue-music-finder)
 * باید بالا باشن؛ این دستور فقط «شبیه‌سازی ورودی» می‌کنه، پردازش واقعی
 * (دانلود، ارسال) هنوز از همون Jobها و صف‌های همیشگی رد می‌شه.
 *
 * مثال‌ها:
 *   php artisan bot:simulate telegram text "حرمسرا تتلو"
 *   php artisan bot:simulate telegram link "https://www.youtube.com/watch?v=xxxx"
 *   php artisan bot:simulate telegram command "/new-song"
 *   php artisan bot:simulate telegram audio /var/www/storage/app/test.mp3
 *   php artisan bot:simulate bale text "سلام"
 *   php artisan bot:simulate telegram callback "react_like_123" --message-id=456
 */
class SimulateBotMessage extends Command
{
    protected $signature = 'bot:simulate
        {platform : telegram یا bale}
        {type : text | command | link | callback | audio | video | voice}
        {content? : متن پیام (برای text/command/link/callback) یا مسیر فایل محلی (برای audio/video/voice)}
        {--message-id= : فقط برای type=callback؛ شناسه‌ی پیامی که دکمه‌هاش روشه}
        {--as-user= : (اختیاری) به‌جای ادمین، این شناسه‌ی کاربری رو به‌عنوان فرستنده جا بزن}';

    protected $description = 'شبیه‌سازی یه پیام یا کلیک ورودی از تلگرام/بله، برای تست کامل مسیر بات بدون نیاز به وب‌هوک واقعی';

    public function handle(TelegramActionService $telegramAction, BaleActionService $baleAction): int
    {
        $platform = $this->argument('platform');

        if (!in_array($platform, ['telegram', 'bale'], true)) {
            $this->error('پلتفرم باید telegram یا bale باشه.');
            return self::FAILURE;
        }

        // پیش‌فرض: چت مقصد = خود ادمین همون پلتفرم (که نتیجه رو توی چت خودت ببینی)
        $userId = $this->option('as-user')
            ?: config($platform === 'bale' ? 'telegram.bale_admin_id' : 'telegram.telegram_admin_id');

        if (!$userId) {
            $this->error("شناسه‌ی ادمین برای {$platform} توی .env تنظیم نشده (TELEGRAM_ADMIN_ID / BALE_ADMIN_ID).");
            return self::FAILURE;
        }

        $type = $this->argument('type');
        $content = $this->argument('content');
        $actionService = $platform === 'bale' ? $baleAction : $telegramAction;

        $this->info("در حال ساخت پیام قلابی ({$type}) برای {$platform}، چت مقصد: {$userId}...");

        try {
            match ($type) {
                'text', 'command', 'link' => $this->simulateTextMessage($actionService, (int) $userId, $content ?? ''),
                'callback' => $this->simulateCallback($actionService, (int) $userId, $content ?? '', (int) $this->option('message-id')),
                'audio', 'video', 'voice' => $this->simulateMediaMessage($actionService, $platform, (int) $userId, $type, $content ?? ''),
                default => throw new \InvalidArgumentException("نوع نامعتبر: {$type}"),
            };
        } catch (\Throwable $e) {
            $this->error('خطا: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('✅ پیام قلابی پردازش شد.');
        $this->line('اگه یه Job دیسپچ شده باشه (دانلود/جستجوی پیشرفته)، نتیجه‌ی نهایی چند لحظه بعد میاد. برای دیدن پردازش زنده:');
        $this->line('docker compose logs -f queue-download queue-music-finder');

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────
    // ساخت پیام متنی قلابی (شامل لینک و دستورات /start ، /new-song و ...)
    // ─────────────────────────────────────────────────
    private function simulateTextMessage($actionService, int $userId, string $text): void
    {
        if ($text === '') {
            $this->error('برای type=text/command/link باید متن پیام رو هم بدی.');
            return;
        }

        $message = $this->baseMessagePayload($userId);
        $message['text'] = $text;

        $request = Request::create('/', 'POST', ['message' => $message]);
        $actionService->handleMessage($request);
    }

    // ─────────────────────────────────────────────────
    // ساخت callback_query قلابی (شبیه‌سازی کلیک روی دکمه)
    // ─────────────────────────────────────────────────
    private function simulateCallback($actionService, int $userId, string $data, int $messageId): void
    {
        if ($data === '' || $messageId === 0) {
            $this->error('برای type=callback باید هم content (متن callback_data) و هم --message-id= رو بدی.');
            return;
        }

        $sourceMessage = $this->baseMessagePayload($userId);
        unset($sourceMessage['from']); // پیام مبدا (که دکمه روشه) مال خود بات بود، نه کاربر

        $callback = [
            'id'      => 'test-callback-' . uniqid(),
            'from'    => $this->fakeUser($userId),
            'message' => array_merge($sourceMessage, ['message_id' => $messageId]),
            'data'    => $data,
        ];

        $request = Request::create('/', 'POST', ['callback_query' => $callback]);
        $actionService->handleCallback($request);
    }

    // ─────────────────────────────────────────────────
    // ساخت پیام رسانه‌ای قلابی (audio/video/voice)
    //
    // چون تلگرام/بله فایل رو با file_id شناسایی می‌کنن (نه فایل خام)،
    // اول فایل محلی رو واقعاً از طریق بات به چت خود ادمین می‌فرستیم
    // (دقیقاً مثل یه پیام واقعی)، بعد همون file_id واقعی که برگشت رو
    // توی پیام ورودی قلابی می‌ذاریم — یعنی از دید بقیه‌ی کد، این دقیقاً
    // یه فایل واقعیه که کاربر فرستاده، نه یه چیز جعلی.
    // ─────────────────────────────────────────────────
    private function simulateMediaMessage($actionService, string $platform, int $userId, string $type, string $localPath): void
    {
        if ($localPath === '' || !file_exists($localPath)) {
            $this->error('برای type=audio/video/voice باید مسیر یه فایل واقعی روی دیسک کانتینر بدی (مثلاً /var/www/storage/app/test.mp3).');
            return;
        }

        $this->line('در حال آپلود فایل تستی به چت خودت (برای گرفتن file_id واقعی)...');

        $token   = $platform === 'bale' ? config('telegram.bale_bot_token') : config('telegram.bot_token');
        $baseUrl = $platform === 'bale' ? "https://tapi.bale.ai/bot{$token}" : "https://api.telegram.org/bot{$token}";

        [$endpoint, $field] = match ($type) {
            'video' => ['sendVideo', 'video'],
            'voice' => ['sendVoice', 'voice'],
            default => ['sendAudio', 'audio'],
        };

        $response = Http::attach($field, fopen($localPath, 'r'), basename($localPath))
            ->post("{$baseUrl}/{$endpoint}", ['chat_id' => (string) $userId]);

        if (!$response->successful()) {
            $this->error('آپلود فایل تستی ناموفق بود: ' . $response->json('description', 'خطای ناشناخته'));
            return;
        }

        $mediaObject = $response->json("result.{$field}");
        if (!$mediaObject) {
            $this->error('پاسخ آپلود فرمت مورد انتظار رو نداشت.');
            return;
        }

        $message = $this->baseMessagePayload($userId);
        $message[$field] = $mediaObject;

        $request = Request::create('/', 'POST', ['message' => $message]);
        $actionService->handleMessage($request);
    }

    // ─────────────────────────────────────────────────
    // اسکلت مشترک یه پیام (chat + from + message_id تصادفی)
    // ─────────────────────────────────────────────────
    private function baseMessagePayload(int $userId): array
    {
        return [
            'message_id' => random_int(900000000, 999999999), // شناسه‌ی قلابی، فقط برای یکتا بودن
            'chat'       => ['id' => $userId, 'type' => 'private'],
            'from'       => $this->fakeUser($userId),
            'date'       => time(),
        ];
    }

    private function fakeUser(int $userId): array
    {
        return [
            'id'         => $userId,
            'first_name' => 'Test',
            'username'   => 'test_admin',
        ];
    }
}
