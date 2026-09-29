<?php

namespace App\Bot\Shared;

use App\Bot\BotUserService;
use App\Bot\NotificationService;
use App\Models\Song;
use App\Models\SongRequest;
use App\Services\AdminNotifier;
use App\Services\MusicFinder\MusicFinderAccess;
use App\Services\MusicFinder\MusicFinderUsageService;
use App\Services\SongReactionService;
use App\Services\SongUploadService;
use App\Jobs\MusicFinderJob;
use App\Jobs\ProcessBaleMusicDownloadJob;
use App\Jobs\ProcessMusicDownloadJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class BaseBotActionService
{
    private const MAX_SEARCH_TEXT_LENGTH = 200;

    public function __construct(
        protected BotUserService $botUserService,
        protected NotificationService $notifier,
        protected AdminNotifier $adminNotifier,
        protected MembershipService $membershipService,
        protected BotAdminService $adminService,
        protected SongUploadService $songUploadService,
        protected SongReactionService $reactionService,
        protected MessageProcessorInterface $messageProcessor,
    ) {}

    // ─────────────────────────────────────────────────
    // Hookهای مخصوص هر پلتفرم
    // ─────────────────────────────────────────────────

    abstract protected function platform(): string;
    abstract protected function isGroupOrChannelMessage(array $message): bool;
    abstract protected function resolveUserName(array $from): string;
    abstract protected function membershipCheckCallbackData(): string;
    abstract protected function handleGenericMediaMessage(BotApiInterface $bot, array $message): void;

    protected function bot(): BotApiInterface
    {
        return BotFactory::make($this->platform());
    }

    // ─────────────────────────────────────────────────
    // Template Methodهای اصلی
    // ─────────────────────────────────────────────────

    protected function processMessage(array $message): void
    {
        // اگه پیام از کانال یا گروه اومده بود نادیده بگیر
        if ($this->isGroupOrChannelMessage($message)) {
            return;
        }

        $bot      = $this->bot();
        $userId   = $message['from']['id'];
        $userName = $this->resolveUserName($message['from']);
        $chatId   = $message['chat']['id'];

        $this->registerAndMaybeNotify($bot, $message, $userId);

        if ($this->adminService->isAdmin($bot, (int) $userId)) {
            if ($this->adminService->hasActiveFlow($bot, (int) $userId)) {
                $this->adminService->handleMessage($bot, $message);
                return;
            }
            if ($this->adminService->tryStartCommand($bot, $message)) {
                return;
            }
        }

        // اگه لینک دانلود از سایت بود همون آهنگ رو براش بفرست
        // عضویت هم لازم نیست، بعد این چک میشه
        if ($songId = $this->extractDirectDownloadSongId($message)) {
            $this->dispatchDirectDownload($chatId, $songId);
            return;
        }

        $missing = $this->membershipService->getMissingChannels($bot, $userId);

        if (empty($missing)) {
            $this->forgetPendingMessage($userId);
            $this->forgetLastJoinMessage($userId);

            if ($this->isUnsupportedContentMessage($message)) {
                $bot->sendMessage($chatId, 'متوجه منظورت نشدم! متن آهنگ، لینک اینستا یا یوتیوب، ویدیو یا قسمتی از آهنگ رو ضبط کن و بفرست 🎵');
                return;
            }

            $identifiableFileId = $message['audio']['file_id'] ?? $message['video']['file_id'] ?? $message['voice']['file_id'] ?? null;

            if ($identifiableFileId) {
                if (MusicFinderAccess::allowed($this->platform(), (int) $userId)) {
                    $type = isset($message['audio']) ? 'audio' : (isset($message['video']) ? 'video' : 'voice');
                    $this->dispatchMediaIdentification($bot, $chatId, $identifiableFileId, $type);
                    $this->handleGenericMediaMessage($bot, $message);
                    return;
                }
            }

            if ($this->isMediaMessage($message)) {
                $this->handleGenericMediaMessage($bot, $message);
                return;
            }

            $text = trim($message['text'] ?? '');

            if ($text !== '' && $this->isMusicFinderLink($text)) {
                if (MusicFinderAccess::allowed($this->platform(), (int) $userId)) {
                    $this->dispatchLinkIdentification($bot, $chatId, $text);
                } else {
                    $this->handleLegacyLinkReply($bot, $chatId);
                }
                return;
            }

            $this->routeToCommandProcessor($bot, $message);
            return;
        }

        $this->cachePendingMessage($userId, $message);
        $this->deleteLastJoinMessage($bot, $chatId, $userId);
        $newMessageId = $this->membershipService->sendJoinMessage($bot, $chatId, $missing, $userName);
        if ($newMessageId) {
            $this->rememberLastJoinMessage($userId, $newMessageId);
        }
    }

    protected function processCallback(array $callback): void
    {
        $bot       = $this->bot();
        $userId    = $callback['from']['id'];
        $userName  = $this->resolveUserName($callback['from']);
        $chatId    = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $data      = $callback['data'] ?? '';

        //اگه ادمین داشت کاری میکرد
        if (
            $this->adminService->isAdmin($bot, (int) $userId)
            && ($data === 'admin_cancel' || $this->adminService->hasActiveFlow($bot, (int) $userId))
        ) {
            $this->adminService->handleCallback($bot, $callback);
            return;
        }

        // اگه روی دکمه های لایک و دیس لایک زده بود
        if (str_starts_with($data, 'react_like_') || str_starts_with($data, 'react_dislike_')) {
            $this->handleSongReactionCallback($bot, $callback);
            return;
        }

        //اگه روی دکمه هایی که از سرویس آهنگ یاب اومده کلیک کرد
        if (str_starts_with($data, 'mf_pick_')) {
            $this->handleMusicFinderPick($bot, $callback);
            return;
        }

        // اگه روی دکمه بررسی عضویت کلیک کرد
        if ($data === $this->membershipCheckCallbackData()) {
            $this->handleMembershipCheck($bot, $callback['id'], $chatId, $messageId, $userId, $userName);
            return;
        }

        $missing = $this->membershipService->getMissingChannels($bot, $userId);
        if (!empty($missing)) {
            $this->deleteLastJoinMessage($bot, $chatId, $userId);
            $newMessageId = $this->membershipService->sendJoinMessage($bot, $chatId, $missing, $userName);
            if ($newMessageId) {
                $this->rememberLastJoinMessage($userId, $newMessageId);
            }
            return;
        }

        //اگه روی دکمه های آهنگ خودمون کلیک کرد
        if (str_starts_with($data, 'song_')) {
            $songId = (int) str_replace('song_', '', $data);
            $this->songUploadService->findBestLinkAndSend($callback, $songId, $chatId, $bot);
        } elseif (str_starts_with($data, 'request_song_')) {
            // اگه روی دکمه جستجوی پیشرفته کلیک کرد
            // اول درخواست ذخیره میشه بعد ارسال میشه به سرویس های آهنگ یاب
            $token = str_replace('request_song_', '', $data);
            $this->handleSongRequestClick($bot, $callback['id'], $token, $chatId, $messageId);
        }
    }

    // ─────────────────────────────────────────────────
    // منطق‌های مشترک
    // ─────────────────────────────────────────────────

    private function registerAndMaybeNotify(BotApiInterface $bot, array $message, int $userId): void
    {
        try {
            $this->botUserService->registerFromMessage($message, $this->platform());

            if (!$this->adminService->isAdmin($bot, $userId)) {
                $this->notifier->messageReceived($message, $this->platform());
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to register/notify user', ['platform' => $this->platform(), 'error' => $e->getMessage()]);
        }
    }

    protected function isMediaMessage(array $message): bool
    {
        return isset($message['video'])
            || isset($message['audio'])
            || isset($message['voice'])
            || isset($message['video_note'])
            || isset($message['document']);
    }

    private function isMusicFinderLink(string $text): bool
    {
        return (bool) preg_match('~(?:youtube\.com|youtu\.be|instagram\.com|tiktok\.com)~i', $text);
    }

    private function routeToCommandProcessor(BotApiInterface $bot, array $message): void
    {
        $text = trim($message['text'] ?? '');

        if ($text !== '' && !str_starts_with($text, '/') && mb_strlen($text) > self::MAX_SEARCH_TEXT_LENGTH) {
            $bot->sendMessage(
                $message['chat']['id'],
                "⚠️ متن خیلی طولانیه!\n\n"
                    . "متن جستجو نباید بیشتر از " . self::MAX_SEARCH_TEXT_LENGTH . " کاراکتر باشه.\n"
                    . "لطفاً کوتاه‌ترش کن و دوباره بفرست."
            );
            return;
        }

        $this->messageProcessor->process($message);
    }

    private function handleLegacyLinkReply(BotApiInterface $bot, int|string $chatId): void
    {
        $bot->sendMessage(
            $chatId,
            "✅ لینک اینستاگرام با موفقیت ثبت شد\n\n⏰ آهنگ در صف دانلود قرار گرفت.\n\n💎🫡 وقتی آماده شد، همینجا برات ارسال میکنم!"
        );
    }

    private function extractDirectDownloadSongId(array $message): ?int
    {
        $text = trim($message['text'] ?? '');
        if (!preg_match('/^\/start(@[A-Za-z0-9_]+)?\s+dl_(\d+)\s*$/u', $text, $matches)) {
            return null;
        }
        return (int) $matches[2];
    }

    private function dispatchDirectDownload(int|string $chatId, int $songId): void
    {
        match ($this->platform()) {
            'bale'  => ProcessBaleMusicDownloadJob::dispatch($songId, $chatId, null, null),
            default => ProcessMusicDownloadJob::dispatch($songId, $chatId, null),
        };
    }

    // ─── جستجوی پیشرفته (MusicFinder) ───

    private function dispatchMediaIdentification(BotApiInterface $bot, int|string $chatId, string $fileId, string $type): void
    {
        if ($quotaMessage = MusicFinderUsageService::guard($this->platform(), $chatId)) {
            $bot->sendMessage($chatId, $quotaMessage);
            return;
        }

        if (!MusicFinderAccess::tryReserve($this->platform(), $chatId)) {
            $bot->sendMessage($chatId, '⏳ درخواست قبلی‌ات هنوز در حال پردازشه، صبر کن تموم بشه.');
            return;
        }

        $file = $bot->getFile($fileId);
        if (!$file || empty($file['file_path'])) {
            MusicFinderAccess::release($this->platform(), $chatId);
            $bot->sendMessage($chatId, '❌ دریافت فایل ناموفق بود.');
            return;
        }

        $tempDir = storage_path('app/private/music-finder-incoming/' . Str::uuid());
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $ext = match ($type) {
            'voice' => 'ogg',
            'video' => 'mp4',
            default => 'mp3'
        };
        $localPath = "{$tempDir}/incoming.{$ext}";

        if (!$bot->downloadFile($file['file_path'], $localPath)) {
            MusicFinderAccess::release($this->platform(), $chatId);
            $bot->sendMessage($chatId, '❌ دریافت فایل ناموفق بود.');
            return;
        }

        MusicFinderJob::dispatch($this->platform(), $chatId, 'media', mediaPath: $localPath, mediaType: $type)
            ->onQueue('music-finder');
    }

    private function dispatchLinkIdentification(BotApiInterface $bot, int|string $chatId, string $url): void
    {
        if ($quotaMessage = MusicFinderUsageService::guard($this->platform(), $chatId)) {
            $bot->sendMessage($chatId, $quotaMessage);
            return;
        }

        if (!MusicFinderAccess::tryReserve($this->platform(), $chatId)) {
            $bot->sendMessage($chatId, '⏳ درخواست قبلی‌ات هنوز در حال پردازشه، صبر کن تموم بشه.');
            return;
        }

        MusicFinderJob::dispatch($this->platform(), $chatId, 'link', text: $url)->onQueue('music-finder');
    }

    private function handleMusicFinderPick(BotApiInterface $bot, array $callback): void
    {
        $token = str_replace('mf_pick_', '', $callback['data']);
        $cached = Cache::pull("music_finder_btn:{$token}");

        if (!$cached) {
            $bot->answerCallbackQuery($callback['id'], '⏳ این گزینه منقضی شده، دوباره جستجو کن.');
            return;
        }

        $chatId = $callback['message']['chat']['id'];

        if ($quotaMessage = MusicFinderUsageService::guard($this->platform(), $chatId)) {
            $bot->answerCallbackQuery($callback['id']);
            $bot->sendMessage($chatId, $quotaMessage);
            return;
        }

        if (!MusicFinderAccess::tryReserve($this->platform(), $chatId)) {
            $bot->answerCallbackQuery($callback['id'], '⏳ درخواست قبلی‌ات هنوز در حال پردازشه.');
            return;
        }

        $bot->answerCallbackQuery($callback['id'], '🔎 در حال آماده‌سازی...');

        MusicFinderJob::dispatch(
            $this->platform(),
            $chatId,
            'select',
            sourceMessageId: $cached['message_id'],
            callbackData: $cached['data'],
            botKey: $cached['bot_key'] ?? null,
        )->onQueue('music-finder');
    }

    // ─── واکنش لایک/دیس‌لایک ───

    private function handleSongReactionCallback(BotApiInterface $bot, array $callback): void
    {
        $data      = $callback['data'];
        $chatId    = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $userId    = $callback['from']['id'];

        $type   = str_starts_with($data, 'react_like_') ? 'like' : 'dislike';
        $songId = (int) str_replace(['react_like_', 'react_dislike_'], '', $data);

        $song = Song::find($songId);
        if (!$song) {
            $bot->answerCallbackQuery($callback['id'], '❌ آهنگ پیدا نشد');
            return;
        }

        $result = $this->reactionService->handleReaction($song, $type, $this->platform(), (string) $userId);

        $toast = match ($result['action']) {
            'added'    => $type === 'like' ? '👍 لایک شد' : '👎 دیس‌لایک شد',
            'switched' => $type === 'like' ? '👍 نظرت به لایک تغییر کرد' : '👎 نظرت به دیس‌لایک تغییر کرد',
            'removed'  => 'نظرت برداشته شد',
        };

        $bot->answerCallbackQuery($callback['id'], $toast);

        $bot->editMessageText(
            $chatId,
            $messageId,
            SongReactionPresenter::messageText(),
            SongReactionPresenter::keyboard($songId, $result['likes_count'], $result['dislikes_count'], $result['user_reaction'])
        );
    }

    // ─── چک عضویت ───

    private function handleMembershipCheck(
        BotApiInterface $bot,
        string $callbackQueryId,
        int|string $chatId,
        int $messageId,
        int $userId,
        string $userName
    ): void {
        $bot->deleteMessage($chatId, $messageId);
        $this->forgetLastJoinMessage($userId);

        $missing = $this->membershipService->getMissingChannels($bot, $userId);

        if (empty($missing)) {
            $bot->answerCallbackQuery($callbackQueryId, '✅ عضویت تأیید شد!');
            $pendingMessage = $this->pullPendingMessage($userId);

            if ($pendingMessage) {
                $this->routeToCommandProcessor($bot, $pendingMessage);
            } else {
                $this->membershipService->sendSuccessMessage($bot, $chatId, $userName);
            }
        } else {
            $bot->answerCallbackQuery($callbackQueryId);
            $newMessageId = $this->membershipService->sendJoinMessage($bot, $chatId, $missing, $userName);
            if ($newMessageId) {
                $this->rememberLastJoinMessage($userId, $newMessageId);
            }
        }
    }

    // ─── ثبت درخواست آهنگ (دکمه‌ی جستجوی پیشرفته) ───

    private function handleSongRequestClick(BotApiInterface $bot, string $callbackQueryId, string $token, int|string $chatId, int $messageId): void
    {
        $payload = Cache::pull("song_request:{$token}");

        if (!$payload) {
            $bot->answerCallbackQuery($callbackQueryId, '⚠️ درخواست منقضی شده');
            return;
        }

        try {
            $request = SongRequest::create([
                'user_id'    => $payload['from']['id'],
                'username'   => $payload['from']['username'] ?? null,
                'first_name' => $payload['from']['first_name'] ?? null,
                'last_name'  => $payload['from']['last_name'] ?? null,
                'chat_id'    => $payload['chat_id'],
                'query'      => $payload['query'],
                'status'     => SongRequest::STATUS_PENDING,
                'platform'   => $this->platform(),
            ]);

            $statusText = MusicFinderAccess::isTestMode()
                ? "✅ پیامت با موفقیت ثبت شد\n\n⏰ آهنگ در صف دانلود قرار گرفت.\n\n💎🫡 وقتی آماده شد، همینجا برات ارسال میکنم!"
                : "🔎 در حال جستجو، چند لحظه صبر کن...";

            $bot->editMessageText($chatId, $messageId, $statusText, []);
            $bot->answerCallbackQuery($callbackQueryId, '✅ درخواست ثبت شد!');

            $this->adminNotifier->notifyNewSongRequest($request, $this->platform());

            if (MusicFinderAccess::allowed($this->platform(), $payload['from']['id'])) {
                if ($quotaMessage = MusicFinderUsageService::guard($this->platform(), $chatId)) {
                    $bot->sendMessage($chatId, $quotaMessage);
                } elseif (MusicFinderAccess::tryReserve($this->platform(), $chatId)) {
                    MusicFinderJob::dispatch($this->platform(), $chatId, 'text', text: $payload['query'], originalQuery: $payload['query'])
                        ->onQueue('music-finder');
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to save song request', ['platform' => $this->platform(), 'error' => $e->getMessage()]);
            $this->notifier->error('ثبت درخواست آهنگ', $e->getMessage(), $this->platform());
            $bot->answerCallbackQuery($callbackQueryId, '❌ خطا در ثبت درخواست');
        }
    }

    // ─── کش پیام در انتظار / آخرین پیام «عضو شو» ───

    private function pendingMessageKey(int $userId): string
    {
        return "{$this->platform()}:pending_message:{$userId}";
    }

    private function cachePendingMessage(int $userId, array $message): void
    {
        $ttl = (int) config('telegram.pending_message_ttl', 10);
        Cache::put($this->pendingMessageKey($userId), $message, now()->addMinutes($ttl));
    }

    private function pullPendingMessage(int $userId): ?array
    {
        return Cache::pull($this->pendingMessageKey($userId));
    }

    private function forgetPendingMessage(int $userId): void
    {
        Cache::forget($this->pendingMessageKey($userId));
    }

    private function lastJoinMessageKey(int $userId): string
    {
        return "{$this->platform()}:last_join_message:{$userId}";
    }

    private function rememberLastJoinMessage(int $userId, int $messageId): void
    {
        $ttl = (int) config('telegram.pending_message_ttl', 10);
        Cache::put($this->lastJoinMessageKey($userId), $messageId, now()->addMinutes($ttl));
    }

    private function deleteLastJoinMessage(BotApiInterface $bot, int|string $chatId, int $userId): void
    {
        $lastMessageId = Cache::pull($this->lastJoinMessageKey($userId));
        if (!$lastMessageId) {
            return;
        }

        $response = $bot->deleteMessage($chatId, (int) $lastMessageId);
        if (!$response->successful()) {
            Log::debug('Could not delete previous join message', ['chat_id' => $chatId, 'message_id' => $lastMessageId]);
        }
    }

    private function forgetLastJoinMessage(int $userId): void
    {
        Cache::forget($this->lastJoinMessageKey($userId));
    }

    private function isUnsupportedContentMessage(array $message): bool
    {
        return isset($message['sticker'])
            || isset($message['animation'])
            || isset($message['photo'])
            || isset($message['location'])
            || isset($message['contact'])
            || isset($message['poll'])
            || isset($message['dice'])
            || isset($message['game'])
            || isset($message['venue']);
    }
}
