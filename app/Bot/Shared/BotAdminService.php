<?php

namespace App\Bot\Shared;

use App\Helpers\SongSlugHelper;
use App\Jobs\SendBroadcastMessageJob;
use App\Models\BotUser;
use App\Models\Song;
use App\Models\SongDownloadLink;
use App\Services\ArtistResolver;
use App\Services\ImageUploadService;
use Babak\Elasticsearch\Facades\Elasticsearch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BotAdminService
{
    public function __construct(private ImageUploadService $images) {}

    // ─────────────────────────────────────────────────
    // API عمومی
    // ─────────────────────────────────────────────────

    public function isAdmin(BotApiInterface $bot, int $userId): bool
    {
        $adminId = $bot->platform() === 'bale'
            ? config('telegram.bale_admin_id')
            : config('telegram.telegram_admin_id');

        return $adminId && (string) $userId === (string) $adminId;
    }

    public function hasActiveFlow(BotApiInterface $bot, int $userId): bool
    {
        return $this->getFlow($bot, $userId) !== null;
    }

    public function tryStartCommand(BotApiInterface $bot, array $message): bool
    {
        $userId = $message['from']['id'];
        $chatId = $message['chat']['id'];
        $text   = trim($message['text'] ?? '');

        return match ($text) {
            '/new-song'     => $this->startNewSong($bot, $userId, $chatId),
            '/send-message' => $this->startSendMessage($bot, $userId, $chatId),
            '/broadcast'    => $this->startBroadcast($bot, $userId, $chatId),
            default         => false,
        };
    }

    public function handleMessage(BotApiInterface $bot, array $message): void
    {
        $userId = $message['from']['id'];
        $flow   = $this->getFlow($bot, $userId);

        if (!$flow) {
            return;
        }

        if (trim($message['text'] ?? '') === '/cancel') {
            $this->cancel($bot, $userId, $message['chat']['id']);
            return;
        }

        match ($flow['flow']) {
            'new_song'     => $this->handleNewSongStep($bot, $message, $flow),
            'send_message' => $this->handleSendMessageStep($bot, $message, $flow),
            'broadcast'    => $this->handleBroadcastStep($bot, $message, $flow),
            default        => null,
        };
    }

    public function handleCallback(BotApiInterface $bot, array $callback): void
    {
        $userId    = $callback['from']['id'];
        $chatId    = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $data      = $callback['data'] ?? '';

        $bot->answerCallbackQuery($callback['id']);

        if ($data === 'admin_cancel') {
            $this->cancel($bot, $userId, $chatId, $messageId);
            return;
        }

        $flow = $this->getFlow($bot, $userId);
        if (!$flow) {
            return;
        }

        match ($flow['flow']) {
            'new_song'  => $this->handleNewSongCallback($bot, $callback, $flow),
            'broadcast' => $this->handleBroadcastCallback($bot, $callback, $flow),
            default     => null,
        };
    }

    // ─────────────────────────────────────────────────
    // Cache state + کمکی‌های نمایش/پاکسازی پیام
    // ─────────────────────────────────────────────────

    private function flowKey(BotApiInterface $bot, int $userId): string
    {
        return "admin_flow:{$bot->platform()}:{$userId}";
    }

    private function getFlow(BotApiInterface $bot, int $userId): ?array
    {
        return Cache::get($this->flowKey($bot, $userId));
    }

    private function setFlow(BotApiInterface $bot, int $userId, array $flow): void
    {
        Cache::put($this->flowKey($bot, $userId), $flow, now()->addMinutes(30));
    }

    private function clearFlow(BotApiInterface $bot, int $userId): void
    {
        Cache::forget($this->flowKey($bot, $userId));
    }

    private function prompt(BotApiInterface $bot, int $userId, int|string $chatId, string $text, ?array $keyboard = null): void
    {
        $flow = $this->getFlow($bot, $userId);

        if ($flow && !empty($flow['prompt_message_id'])) {
            $bot->deleteMessage($chatId, $flow['prompt_message_id']);
        }

        $response = $bot->sendMessage($chatId, $text, $keyboard ?? $this->cancelKeyboard());

        if ($flow) {
            $flow['prompt_message_id'] = $response->successful() ? $response->json('result.message_id') : null;
            $this->setFlow($bot, $userId, $flow);
        }
    }

    private function finishWithMessage(BotApiInterface $bot, int $userId, int|string $chatId, string $text): void
    {
        $flow = $this->getFlow($bot, $userId);

        if ($flow && !empty($flow['prompt_message_id'])) {
            $bot->deleteMessage($chatId, $flow['prompt_message_id']);
        }

        $this->clearFlow($bot, $userId);
        $bot->sendMessage($chatId, $text);
    }

    private function cancel(BotApiInterface $bot, int $userId, int|string $chatId, ?int $messageId = null): void
    {
        $flow = $this->getFlow($bot, $userId);

        if (!empty($flow['data']['cover_temp_path']) && file_exists($flow['data']['cover_temp_path'])) {
            @unlink($flow['data']['cover_temp_path']);
        }

        $targetMessageId = $messageId ?? ($flow['prompt_message_id'] ?? null);
        $this->clearFlow($bot, $userId);

        if ($targetMessageId) {
            $bot->editMessageText($chatId, $targetMessageId, '❌ عملیات لغو شد.', []);
        } else {
            $bot->sendMessage($chatId, '❌ عملیات لغو شد.');
        }
    }

    private function cancelKeyboard(): array
    {
        return [[['text' => '❌ لغو', 'callback_data' => 'admin_cancel']]];
    }

    // ═══════════════════════════════════════════════════
    // فلو ۱: ساخت آهنگ
    // ═══════════════════════════════════════════════════

    private function startNewSong(BotApiInterface $bot, int $userId, int|string $chatId): bool
    {
        $this->setFlow($bot, $userId, ['flow' => 'new_song', 'step' => 'song_source', 'data' => []]);
        $this->prompt($bot, $userId, $chatId, '🎵 آهنگ رو بفرست (فایل صوتی) یا لینک مستقیم دانلودش رو بفرست:');
        return true;
    }

    private function handleNewSongStep(BotApiInterface $bot, array $message, array $flow): void
    {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $data   = $flow['data'];

        switch ($flow['step']) {
            case 'song_source':
                $this->handleSongSourceStep($bot, $message, $flow);
                return;

            case 'source_url':
                $text = trim($message['text'] ?? '');
                if ($text === '') {
                    $this->prompt($bot, $userId, $chatId, 'آدرس منبع نمی‌تونه خالی باشه، دوباره وارد کن:');
                    return;
                }
                $data['source_url'] = $text;
                $this->advanceNewSong($bot, $userId, $data, 'cover', $chatId, '🖼 حالا عکس کاور آهنگ رو بفرست:');
                return;

            case 'cover':
                $this->handleCoverStep($bot, $message, $flow);
                return;

            case 'title':
                $text = trim($message['text'] ?? '');
                if ($text === '') {
                    $this->prompt($bot, $userId, $chatId, 'اسم آهنگ نمی‌تونه خالی باشه:');
                    return;
                }
                $data['title'] = $text;
                $this->advanceNewSong($bot, $userId, $data, 'artist', $chatId, '🎤 اسم خواننده رو وارد کن:');
                return;

            case 'artist':
                $text = trim($message['text'] ?? '');
                if ($text === '') {
                    $this->prompt($bot, $userId, $chatId, 'اسم خواننده نمی‌تونه خالی باشه:');
                    return;
                }
                $data['artist_name'] = $text;
                $this->advanceNewSong($bot, $userId, $data, 'lyrics', $chatId, '📝 متن آهنگ رو وارد کن:');
                return;

            case 'lyrics':
                $data['lyrics'] = trim($message['text'] ?? '') ?: null;
                $this->setFlow($bot, $userId, ['flow' => 'new_song', 'step' => 'show_in_site', 'data' => $data]);
                $this->prompt($bot, $userId, $chatId, '🌐 توی سایت نشون داده بشه؟', $this->showSiteKeyboard());
                return;

            case 'show_in_site':
                $this->prompt($bot, $userId, $chatId, 'لطفاً یکی از دکمه‌های بالا رو بزن.', $this->showSiteKeyboard());
                return;
        }
    }

    private function showSiteKeyboard(): array
    {
        return [
            [
                ['text' => '✅ آره', 'callback_data' => 'admin_show_site_yes'],
                ['text' => '❌ نه', 'callback_data' => 'admin_show_site_no'],
            ],
            [['text' => '❌ لغو', 'callback_data' => 'admin_cancel']],
        ];
    }

    private function advanceNewSong(BotApiInterface $bot, int $userId, array $data, string $nextStep, int|string $chatId, string $promptText): void
    {
        $this->setFlow($bot, $userId, ['flow' => 'new_song', 'step' => $nextStep, 'data' => $data]);
        $this->prompt($bot, $userId, $chatId, $promptText);
    }

    private function handleSongSourceStep(BotApiInterface $bot, array $message, array $flow): void
    {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $data   = $flow['data'];

        $fileId = $message['audio']['file_id'] ?? $message['document']['file_id'] ?? $message['voice']['file_id'] ?? null;

        if ($fileId) {
            $this->prompt($bot, $userId, $chatId, '⏳ در حال دریافت و آماده‌سازی فایل...');

            $mp3Path = $this->downloadAndConvertToMp3($bot, $fileId);

            if (!$mp3Path) {
                $this->prompt($bot, $userId, $chatId, '❌ دریافت یا تبدیل فایل ناموفق بود. دوباره فایل رو بفرست یا لینک بده:');
                return;
            }

            $publicName = $this->generateUniqueSongFileName();
            $finalPath  = public_path("{$publicName}.mp3");

            if (!@rename($mp3Path, $finalPath)) {
                @unlink($mp3Path);
                Log::error('rename to public failed in admin new-song flow', ['target' => $finalPath]);
                $this->prompt($bot, $userId, $chatId, '❌ ذخیره فایل نهایی توی public ناموفق بود. دوباره امتحان کن:');
                return;
            }

            $baseUrl = rtrim(config('telegram.public_songs_base_url'), '/');
            $data['download_link_320'] = "{$baseUrl}/{$publicName}.mp3";
            $data['source_url']        = $this->getSiteDomain($baseUrl);

            $this->advanceNewSong($bot, $userId, $data, 'cover', $chatId, "✅ فایل دریافت شد.\n🖼 حالا عکس کاور آهنگ رو بفرست:");
            return;
        }

        $text = trim($message['text'] ?? '');

        if ($text !== '' && filter_var($text, FILTER_VALIDATE_URL)) {
            $data['download_link_320'] = $text;
            $this->advanceNewSong($bot, $userId, $data, 'source_url', $chatId, '🔗 آدرس منبع (source_url) رو وارد کن، مثلاً: mybia2music.com');
            return;
        }

        $this->prompt($bot, $userId, $chatId, 'یا فایل صوتی بفرست یا یه لینک معتبر بفرست:');
    }

    private function downloadAndConvertToMp3(BotApiInterface $bot, string $fileId): ?string
    {
        $file = $bot->getFile($fileId);
        if (!$file || empty($file['file_path'])) {
            return null;
        }

        $uuid      = (string) Str::uuid();
        $sourceExt = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION)) ?: 'bin';
        $rawPath   = public_path(".tmp-{$uuid}.{$sourceExt}");

        if (!$bot->downloadFile($file['file_path'], $rawPath)) {
            @unlink($rawPath);
            return null;
        }

        if ($sourceExt === 'mp3') {
            return $rawPath;
        }

        $mp3Path = public_path(".tmp-{$uuid}.mp3");
        $command = sprintf(
            'ffmpeg -i %s -vn -ar 44100 -ac 2 -b:a 192k %s -y 2>&1',
            escapeshellarg($rawPath),
            escapeshellarg($mp3Path)
        );
        exec($command, $lines, $exit);

        @unlink($rawPath);

        if ($exit !== 0 || !file_exists($mp3Path)) {
            Log::error('ffmpeg convert failed in admin new-song flow', ['output' => $lines]);
            @unlink($mp3Path);
            return null;
        }

        return $mp3Path;
    }

    private function generateUniqueSongFileName(): string
    {
        do {
            $name   = (string) random_int(100000000000000, 999999999999999);
            $exists = file_exists(public_path("{$name}.mp3"))
                || Song::where('download_link_320', 'like', "%/{$name}.mp3")->exists();
        } while ($exists);

        return $name;
    }

    private function handleCoverStep(BotApiInterface $bot, array $message, array $flow): void
    {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $data   = $flow['data'];

        $photos = $message['photo'] ?? null;
        if (!$photos) {
            $this->prompt($bot, $userId, $chatId, 'لطفاً یه عکس بفرست:');
            return;
        }

        $fileId = end($photos)['file_id'];

        $file = $bot->getFile($fileId);
        if (!$file || empty($file['file_path'])) {
            $this->prompt($bot, $userId, $chatId, '❌ دریافت عکس ناموفق بود، دوباره بفرست:');
            return;
        }

        $tempDir = storage_path('app/private/admin-flow/' . Str::uuid());
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $ext = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION)) ?: 'jpg';
        $coverPath = "{$tempDir}/cover.{$ext}";

        if (!$bot->downloadFile($file['file_path'], $coverPath)) {
            $this->prompt($bot, $userId, $chatId, '❌ دریافت عکس ناموفق بود، دوباره بفرست:');
            return;
        }

        $data['cover_temp_path'] = $coverPath;
        $this->advanceNewSong($bot, $userId, $data, 'title', $chatId, '🎧 اسم آهنگ رو وارد کن:');
    }

    private function handleNewSongCallback(BotApiInterface $bot, array $callback, array $flow): void
    {
        if ($flow['step'] !== 'show_in_site' || !in_array($callback['data'], ['admin_show_site_yes', 'admin_show_site_no'], true)) {
            return;
        }

        $chatId    = $callback['message']['chat']['id'];
        $userId    = $callback['from']['id'];
        $messageId = $callback['message']['message_id'];
        $data      = $flow['data'];

        $data['show_in_saz_site'] = $callback['data'] === 'admin_show_site_yes' ? 1 : 0;

        $this->finalizeNewSong($bot, $userId, $chatId, $messageId, $data);
    }

    private function finalizeNewSong(BotApiInterface $bot, int $userId, int|string $chatId, int $messageId, array $data): void
    {
        try {
            $slug = SongSlugHelper::make($data['title'] . ' ' . $data['artist_name']);

            $coverPath = null;
            if (!empty($data['cover_temp_path']) && file_exists($data['cover_temp_path'])) {
                $uploadedCover = new UploadedFile(
                    $data['cover_temp_path'],
                    basename($data['cover_temp_path']),
                    mime_content_type($data['cover_temp_path']),
                    null,
                    true
                );

                $coverPath = $this->images->upload($uploadedCover, '', [
                    'width'        => 400,
                    'format'       => 'webp',
                    'quality'      => 90,
                    'crop'         => false,
                    'date_folders' => true,
                    'filename'     => $slug,
                ]);
            }

            $song = Song::create([
                'title'             => $data['title'],
                'artist_name'       => $data['artist_name'],
                'slug'              => $slug,
                'source_url'        => $data['source_url'],
                'download_link_320' => $data['download_link_320'],
                'lyrics'            => $data['lyrics'] ?? null,
                'show_in_saz_site'  => $data['show_in_saz_site'] ?? 0,
                'cover_path'        => $coverPath,
            ]);

            Elasticsearch::sync($song);
            ArtistResolver::attachSingleArtist($song);

            $domain = $this->getSiteDomain($song->source_url);
            SongDownloadLink::firstOrCreate(
                ['provider' => $domain, 'download_url' => $data['download_link_320']],
                ['song_id' => $song->id, 'quality' => '320']
            );

            if (!empty($data['cover_temp_path'])) {
                @unlink($data['cover_temp_path']);
            }

            $this->clearFlow($bot, $userId);

            $bot->editMessageText(
                $chatId,
                $messageId,
                "✅ آهنگ با موفقیت ثبت شد!\n\n🎧 {$song->title} - {$song->artist_name}\n🆔 شناسه: {$song->id}",
                []
            );
        } catch (\Throwable $e) {
            Log::error('Admin new-song flow failed', ['error' => $e->getMessage()]);
            $this->clearFlow($bot, $userId);
            $bot->editMessageText($chatId, $messageId, '❌ خطا در ثبت آهنگ: ' . $e->getMessage(), []);
        }
    }

    private function getSiteDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? $url;
        return str_replace('www.', '', $host);
    }

    // ═══════════════════════════════════════════════════
    // فلو ۲: پیام به یک کاربر
    // ═══════════════════════════════════════════════════

    private function startSendMessage(BotApiInterface $bot, int $userId, int|string $chatId): bool
    {
        $this->setFlow($bot, $userId, ['flow' => 'send_message', 'step' => 'user_id', 'data' => []]);
        $this->prompt($bot, $userId, $chatId, '🆔 شناسه کاربر (user_id) رو وارد کن:');
        return true;
    }

    private function handleSendMessageStep(BotApiInterface $bot, array $message, array $flow): void
    {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $data   = $flow['data'];

        if ($flow['step'] === 'user_id') {
            $target = trim($message['text'] ?? '');

            if (!ctype_digit($target)) {
                $this->prompt($bot, $userId, $chatId, 'شناسه باید فقط عدد باشه، دوباره وارد کن:');
                return;
            }

            $exists = $bot->platform() === 'bale'
                ? BotUser::bale()->where('user_id', $target)->exists()
                : BotUser::telegram()->where('user_id', $target)->exists();

            if (!$exists) {
                $platformLabel = $bot->platform() === 'bale' ? 'بله' : 'تلگرام';
                $this->prompt($bot, $userId, $chatId, "❌ کاربری با این شناسه توی کاربران {$platformLabel} پیدا نشد. دوباره وارد کن:");
                return;
            }

            $data['target_user_id'] = $target;
            $this->advanceSendMessage($bot, $userId, $data, $chatId, '✍️ حالا پیامی که می‌خوای براش بفرستی رو الان ارسال کن (متن، عکس، ویدیو یا هرچی):');
            return;
        }

        if ($flow['step'] === 'await_message') {
            $targetUserId = $data['target_user_id'];
            $response = $bot->copyMessage($chatId, $message['message_id'], $targetUserId);

            if ($response->successful()) {
                $this->finishWithMessage($bot, $userId, $chatId, "✅ پیام برای کاربر {$targetUserId} ارسال شد.");
            } else {
                $this->finishWithMessage($bot, $userId, $chatId, '❌ ارسال ناموفق بود: ' . $response->json('description', 'خطای ناشناخته'));
            }
        }
    }

    private function advanceSendMessage(BotApiInterface $bot, int $userId, array $data, int|string $chatId, string $promptText): void
    {
        $this->setFlow($bot, $userId, ['flow' => 'send_message', 'step' => 'await_message', 'data' => $data]);
        $this->prompt($bot, $userId, $chatId, $promptText);
    }

    // ═══════════════════════════════════════════════════
    // فلو ۳: پیام همگانی
    // ═══════════════════════════════════════════════════

    private function startBroadcast(BotApiInterface $bot, int $userId, int|string $chatId): bool
    {
        $this->setFlow($bot, $userId, ['flow' => 'broadcast', 'step' => 'await_message', 'data' => []]);
        $this->prompt($bot, $userId, $chatId, '📢 پیامی که می‌خوای برای همه کاربرا ارسال بشه رو الان بفرست:');
        return true;
    }

    private function handleBroadcastStep(BotApiInterface $bot, array $message, array $flow): void
    {
        if ($flow['step'] !== 'await_message') {
            return;
        }

        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $count  = $bot->platform() === 'bale'
            ? BotUser::bale()->where('is_blocked', false)->count()
            : BotUser::telegram()->where('is_blocked', false)->count();

        $platformLabel = $bot->platform() === 'bale' ? 'بله' : 'تلگرام';

        $data = [
            'source_chat_id'    => $chatId,
            'source_message_id' => $message['message_id'],
        ];

        $this->setFlow($bot, $userId, ['flow' => 'broadcast', 'step' => 'confirm', 'data' => $data]);

        $this->prompt($bot, $userId, $chatId, "⚠️ این پیام به {$count} کاربر {$platformLabel} ارسال می‌شه. مطمئنی؟", [
            [
                ['text' => '✅ بله، ارسال کن', 'callback_data' => 'admin_broadcast_confirm'],
                ['text' => '❌ لغو', 'callback_data' => 'admin_cancel'],
            ],
        ]);
    }

    private function handleBroadcastCallback(BotApiInterface $bot, array $callback, array $flow): void
    {
        if ($flow['step'] !== 'confirm' || $callback['data'] !== 'admin_broadcast_confirm') {
            return;
        }

        $chatId    = $callback['message']['chat']['id'];
        $userId    = $callback['from']['id'];
        $messageId = $callback['message']['message_id'];
        $data      = $flow['data'];

        $userIds = $bot->platform() === 'bale'
            ? BotUser::bale()->where('is_blocked', false)->pluck('user_id')
            : BotUser::telegram()->where('is_blocked', false)->pluck('user_id');

        $rate = $bot->platform() === 'bale'
            ? max(1, (int) config('telegram.bale_broadcast_rate_per_second', 10))
            : max(1, (int) config('telegram.broadcast_rate_per_second', 25));

        foreach ($userIds as $index => $targetId) {
            SendBroadcastMessageJob::dispatch($data['source_chat_id'], $data['source_message_id'], $targetId, $bot->platform())
                ->onQueue('broadcast')
                ->delay(now()->addSeconds(intdiv($index, $rate)));
        }

        $this->clearFlow($bot, $userId);

        $seconds = $userIds->count() > 0 ? intdiv($userIds->count() - 1, $rate) + 1 : 0;
        $bot->editMessageText(
            $chatId,
            $messageId,
            "✅ ارسال به {$userIds->count()} کاربر توی صف قرار گرفت. تقریباً {$seconds} ثانیه طول می‌کشه.",
            []
        );
    }
}
