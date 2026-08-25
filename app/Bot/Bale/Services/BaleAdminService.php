<?php

namespace App\Bot\Bale\Services;

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

class BaleAdminService
{
    public function __construct(
        private BaleService $bale,
        private ImageUploadService $images,
    ) {}

    public function isAdmin(int $userId): bool
    {
        $adminId = config('telegram.bale_admin_id');
        return $adminId && (string) $userId === (string) $adminId;
    }

    public function hasActiveFlow(int $userId): bool
    {
        return $this->getFlow($userId) !== null;
    }

    public function tryStartCommand(array $message): bool
    {
        $userId = $message['from']['id'];
        $chatId = $message['chat']['id'];
        $text   = trim($message['text'] ?? '');

        return match ($text) {
            '/new-song'     => $this->startNewSong($userId, $chatId),
            '/send-message' => $this->startSendMessage($userId, $chatId),
            '/broadcast'    => $this->startBroadcast($userId, $chatId),
            default         => false,
        };
    }

    public function handleMessage(array $message): void
    {
        $userId = $message['from']['id'];
        $flow   = $this->getFlow($userId);

        if (!$flow) {
            return;
        }

        if (trim($message['text'] ?? '') === '/cancel') {
            $this->cancel($userId, $message['chat']['id']);
            return;
        }

        match ($flow['flow']) {
            'new_song'     => $this->handleNewSongStep($message, $flow),
            'send_message' => $this->handleSendMessageStep($message, $flow),
            'broadcast'    => $this->handleBroadcastStep($message, $flow),
            default        => null,
        };
    }

    public function handleCallback(array $callback): void
    {
        $userId    = $callback['from']['id'];
        $chatId    = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $data      = $callback['data'] ?? '';

        $this->bale->answerCallbackQuery($callback['id']);

        if ($data === 'admin_cancel') {
            $this->cancel($userId, $chatId, $messageId);
            return;
        }

        $flow = $this->getFlow($userId);
        if (!$flow) {
            return;
        }

        match ($flow['flow']) {
            'new_song'  => $this->handleNewSongCallback($callback, $flow),
            'broadcast' => $this->handleBroadcastCallback($callback, $flow),
            default     => null,
        };
    }

    private function flowKey(int $userId): string
    {
        return "admin_flow:bale:{$userId}";
    }

    private function getFlow(int $userId): ?array
    {
        return Cache::get($this->flowKey($userId));
    }

    private function setFlow(int $userId, array $flow): void
    {
        Cache::put($this->flowKey($userId), $flow, now()->addMinutes(30));
    }

    private function clearFlow(int $userId): void
    {
        Cache::forget($this->flowKey($userId));
    }

    private function prompt(int $userId, int|string $chatId, string $text, ?array $keyboard = null): void
    {
        $flow = $this->getFlow($userId);

        if ($flow && !empty($flow['prompt_message_id'])) {
            $this->bale->deleteMessage($chatId, $flow['prompt_message_id']);
        }

        $response = $this->bale->sendMessage($chatId, $text, $keyboard ?? $this->cancelKeyboard());

        if ($flow) {
            $flow['prompt_message_id'] = $response->successful() ? $response->json('result.message_id') : null;
            $this->setFlow($userId, $flow);
        }
    }

    private function finishWithMessage(int $userId, int|string $chatId, string $text): void
    {
        $flow = $this->getFlow($userId);

        if ($flow && !empty($flow['prompt_message_id'])) {
            $this->bale->deleteMessage($chatId, $flow['prompt_message_id']);
        }

        $this->clearFlow($userId);
        $this->bale->sendMessage($chatId, $text);
    }

    private function cancel(int $userId, int|string $chatId, ?int $messageId = null): void
    {
        $flow = $this->getFlow($userId);

        if (!empty($flow['data']['cover_temp_path']) && file_exists($flow['data']['cover_temp_path'])) {
            @unlink($flow['data']['cover_temp_path']);
        }

        $targetMessageId = $messageId ?? ($flow['prompt_message_id'] ?? null);
        $this->clearFlow($userId);

        if ($targetMessageId) {
            $this->bale->editMessageText($chatId, $targetMessageId, '❌ عملیات لغو شد.', []);
        } else {
            $this->bale->sendMessage($chatId, '❌ عملیات لغو شد.');
        }
    }

    private function cancelKeyboard(): array
    {
        return [[['text' => '❌ لغو', 'callback_data' => 'admin_cancel']]];
    }

    // ═══════════════════════════════════════════════════
    // فلو ۱: ساخت آهنگ
    // ═══════════════════════════════════════════════════

    private function startNewSong(int $userId, int|string $chatId): bool
    {
        $this->setFlow($userId, ['flow' => 'new_song', 'step' => 'song_source', 'data' => []]);
        $this->prompt($userId, $chatId, '🎵 آهنگ رو بفرست (فایل صوتی) یا لینک مستقیم دانلودش رو بفرست:');
        return true;
    }

    private function handleNewSongStep(array $message, array $flow): void
    {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $data   = $flow['data'];

        switch ($flow['step']) {
            case 'song_source':
                $this->handleSongSourceStep($message, $flow);
                return;

            case 'source_url':
                $text = trim($message['text'] ?? '');
                if ($text === '') {
                    $this->prompt($userId, $chatId, 'آدرس منبع نمی‌تونه خالی باشه، دوباره وارد کن:');
                    return;
                }
                $data['source_url'] = $text;
                $this->advanceNewSong($userId, $data, 'cover', $chatId, '🖼 حالا عکس کاور آهنگ رو بفرست:');
                return;

            case 'cover':
                $this->handleCoverStep($message, $flow);
                return;

            case 'title':
                $text = trim($message['text'] ?? '');
                if ($text === '') {
                    $this->prompt($userId, $chatId, 'اسم آهنگ نمی‌تونه خالی باشه:');
                    return;
                }
                $data['title'] = $text;
                $this->advanceNewSong($userId, $data, 'artist', $chatId, '🎤 اسم خواننده رو وارد کن:');
                return;

            case 'artist':
                $text = trim($message['text'] ?? '');
                if ($text === '') {
                    $this->prompt($userId, $chatId, 'اسم خواننده نمی‌تونه خالی باشه:');
                    return;
                }
                $data['artist_name'] = $text;
                $this->advanceNewSong($userId, $data, 'lyrics', $chatId, '📝 متن آهنگ رو وارد کن:');
                return;

            case 'lyrics':
                $data['lyrics'] = trim($message['text'] ?? '') ?: null;
                $this->setFlow($userId, ['flow' => 'new_song', 'step' => 'show_in_site', 'data' => $data]);
                $this->prompt($userId, $chatId, '🌐 توی سایت نشون داده بشه؟', $this->showSiteKeyboard());
                return;

            case 'show_in_site':
                $this->prompt($userId, $chatId, 'لطفاً یکی از دکمه‌های بالا رو بزن.', $this->showSiteKeyboard());
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

    private function advanceNewSong(int $userId, array $data, string $nextStep, int|string $chatId, string $promptText): void
    {
        $this->setFlow($userId, ['flow' => 'new_song', 'step' => $nextStep, 'data' => $data]);
        $this->prompt($userId, $chatId, $promptText);
    }

    private function handleSongSourceStep(array $message, array $flow): void
    {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $data   = $flow['data'];

        $fileId = $message['audio']['file_id'] ?? $message['document']['file_id'] ?? $message['voice']['file_id'] ?? null;

        if ($fileId) {
            $this->prompt($userId, $chatId, '⏳ در حال دریافت و آماده‌سازی فایل...');

            $mp3Path = $this->downloadAndConvertToMp3($fileId);

            if (!$mp3Path) {
                $this->prompt($userId, $chatId, '❌ دریافت یا تبدیل فایل ناموفق بود. دوباره فایل رو بفرست یا لینک بده:');
                return;
            }

            $publicName = $this->generateUniqueSongFileName();
            $finalPath  = public_path("{$publicName}.mp3");

            if (!@rename($mp3Path, $finalPath)) {
                @unlink($mp3Path);
                Log::error('rename to public failed in bale admin new-song flow', ['target' => $finalPath]);
                $this->prompt($userId, $chatId, '❌ ذخیره فایل نهایی توی public ناموفق بود (دسترسی نوشتن رو چک کن). دوباره امتحان کن:');
                return;
            }

            $baseUrl = rtrim(config('telegram.public_songs_base_url'), '/');
            $data['download_link_320'] = "{$baseUrl}/{$publicName}.mp3";
            $data['source_url']        = $this->getSiteDomain($baseUrl);

            $this->advanceNewSong($userId, $data, 'cover', $chatId, "✅ فایل دریافت شد.\n🖼 حالا عکس کاور آهنگ رو بفرست:");
            return;
        }

        $text = trim($message['text'] ?? '');

        if ($text !== '' && filter_var($text, FILTER_VALIDATE_URL)) {
            $data['download_link_320'] = $text;
            $this->advanceNewSong($userId, $data, 'source_url', $chatId, '🔗 آدرس منبع (source_url) رو وارد کن، مثلاً: mybia2music.com');
            return;
        }

        $this->prompt($userId, $chatId, 'یا فایل صوتی بفرست یا یه لینک معتبر بفرست:');
    }

    private function downloadAndConvertToMp3(string $fileId): ?string
    {
        $file = $this->bale->getFile($fileId);
        if (!$file || empty($file['file_path'])) {
            return null;
        }

        $uuid      = (string) Str::uuid();
        $sourceExt = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION)) ?: 'bin';
        $rawPath   = public_path(".tmp-{$uuid}.{$sourceExt}");

        if (!$this->bale->downloadFile($file['file_path'], $rawPath)) {
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
            Log::error('ffmpeg convert failed in bale admin new-song flow', ['output' => $lines]);
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

    private function handleCoverStep(array $message, array $flow): void
    {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $data   = $flow['data'];

        $photos = $message['photo'] ?? null;
        if (!$photos) {
            $this->prompt($userId, $chatId, 'لطفاً یه عکس بفرست:');
            return;
        }

        $fileId = end($photos)['file_id'];

        $file = $this->bale->getFile($fileId);
        if (!$file || empty($file['file_path'])) {
            $this->prompt($userId, $chatId, '❌ دریافت عکس ناموفق بود، دوباره بفرست:');
            return;
        }

        $tempDir = storage_path('app/private/admin-flow/' . Str::uuid());
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $ext = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION)) ?: 'jpg';
        $coverPath = "{$tempDir}/cover.{$ext}";

        if (!$this->bale->downloadFile($file['file_path'], $coverPath)) {
            $this->prompt($userId, $chatId, '❌ دریافت عکس ناموفق بود، دوباره بفرست:');
            return;
        }

        $data['cover_temp_path'] = $coverPath;
        $this->advanceNewSong($userId, $data, 'title', $chatId, '🎧 اسم آهنگ رو وارد کن:');
    }

    private function handleNewSongCallback(array $callback, array $flow): void
    {
        if ($flow['step'] !== 'show_in_site' || !in_array($callback['data'], ['admin_show_site_yes', 'admin_show_site_no'], true)) {
            return;
        }

        $chatId    = $callback['message']['chat']['id'];
        $userId    = $callback['from']['id'];
        $messageId = $callback['message']['message_id'];
        $data      = $flow['data'];

        $data['show_in_saz_site'] = $callback['data'] === 'admin_show_site_yes' ? 1 : 0;

        $this->finalizeNewSong($userId, $chatId, $messageId, $data);
    }

    private function finalizeNewSong(int $userId, int|string $chatId, int $messageId, array $data): void
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

            $this->clearFlow($userId);

            $this->bale->editMessageText(
                $chatId,
                $messageId,
                "✅ آهنگ با موفقیت ثبت شد!\n\n🎧 {$song->title} - {$song->artist_name}\n🆔 شناسه: {$song->id}",
                []
            );
        } catch (\Throwable $e) {
            Log::error('Admin new-song flow failed (bale)', ['error' => $e->getMessage()]);
            $this->clearFlow($userId);
            $this->bale->editMessageText($chatId, $messageId, '❌ خطا در ثبت آهنگ: ' . $e->getMessage(), []);
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

    private function startSendMessage(int $userId, int|string $chatId): bool
    {
        $this->setFlow($userId, ['flow' => 'send_message', 'step' => 'user_id', 'data' => []]);
        $this->prompt($userId, $chatId, '🆔 شناسه کاربر (user_id) رو وارد کن:');
        return true;
    }

    private function handleSendMessageStep(array $message, array $flow): void
    {
        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $data   = $flow['data'];

        if ($flow['step'] === 'user_id') {
            $target = trim($message['text'] ?? '');

            if (!ctype_digit($target)) {
                $this->prompt($userId, $chatId, 'شناسه باید فقط عدد باشه، دوباره وارد کن:');
                return;
            }

            if (!BotUser::bale()->where('user_id', $target)->exists()) {
                $this->prompt($userId, $chatId, '❌ کاربری با این شناسه توی کاربران بله پیدا نشد. دوباره وارد کن:');
                return;
            }

            $data['target_user_id'] = $target;
            $this->advanceSendMessage($userId, $data, $chatId, '✍️ حالا پیامی که می‌خوای براش بفرستی رو الان ارسال کن (متن، عکس، ویدیو یا هرچی):');
            return;
        }

        if ($flow['step'] === 'await_message') {
            $targetUserId = $data['target_user_id'];
            $response = $this->bale->copyMessage($chatId, $message['message_id'], $targetUserId);

            if ($response->successful()) {
                $this->finishWithMessage($userId, $chatId, "✅ پیام برای کاربر {$targetUserId} ارسال شد.");
            } else {
                $this->finishWithMessage($userId, $chatId, '❌ ارسال ناموفق بود: ' . $response->json('description', 'خطای ناشناخته'));
            }
        }
    }

    private function advanceSendMessage(int $userId, array $data, int|string $chatId, string $promptText): void
    {
        $this->setFlow($userId, ['flow' => 'send_message', 'step' => 'await_message', 'data' => $data]);
        $this->prompt($userId, $chatId, $promptText);
    }

    // ═══════════════════════════════════════════════════
    // فلو ۳: پیام همگانی
    // ═══════════════════════════════════════════════════

    private function startBroadcast(int $userId, int|string $chatId): bool
    {
        $this->setFlow($userId, ['flow' => 'broadcast', 'step' => 'await_message', 'data' => []]);
        $this->prompt($userId, $chatId, '📢 پیامی که می‌خوای برای همه کاربرا ارسال بشه رو الان بفرست:');
        return true;
    }

    private function handleBroadcastStep(array $message, array $flow): void
    {
        if ($flow['step'] !== 'await_message') {
            return;
        }

        $chatId = $message['chat']['id'];
        $userId = $message['from']['id'];
        $count  = BotUser::bale()->where('is_blocked', false)->count();

        $data = [
            'source_chat_id'    => $chatId,
            'source_message_id' => $message['message_id'],
        ];

        $this->setFlow($userId, ['flow' => 'broadcast', 'step' => 'confirm', 'data' => $data]);

        $this->prompt($userId, $chatId, "⚠️ این پیام به {$count} کاربر بله ارسال می‌شه. مطمئنی؟", [
            [
                ['text' => '✅ بله، ارسال کن', 'callback_data' => 'admin_broadcast_confirm'],
                ['text' => '❌ لغو', 'callback_data' => 'admin_cancel'],
            ],
        ]);
    }

    private function handleBroadcastCallback(array $callback, array $flow): void
    {
        if ($flow['step'] !== 'confirm' || $callback['data'] !== 'admin_broadcast_confirm') {
            return;
        }

        $chatId    = $callback['message']['chat']['id'];
        $userId    = $callback['from']['id'];
        $messageId = $callback['message']['message_id'];
        $data      = $flow['data'];

        $userIds = BotUser::bale()->where('is_blocked', false)->pluck('user_id');
        $rate    = max(1, (int) config('telegram.bale_broadcast_rate_per_second', 10));

        foreach ($userIds as $index => $targetId) {
            SendBroadcastMessageJob::dispatch($data['source_chat_id'], $data['source_message_id'], $targetId, 'bale')
                ->onQueue('broadcast')
                ->delay(now()->addSeconds(intdiv($index, $rate)));
        }

        $this->clearFlow($userId);

        $seconds = $userIds->count() > 0 ? intdiv($userIds->count() - 1, $rate) + 1 : 0;
        $this->bale->editMessageText(
            $chatId,
            $messageId,
            "✅ ارسال به {$userIds->count()} کاربر توی صف قرار گرفت. تقریباً {$seconds} ثانیه طول می‌کشه.",
            []
        );
    }
}
