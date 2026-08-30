<?php

namespace App\Bot\Bale\Services;

use App\Bot\Bale\Actions\BaleShowDownloadOptionsAction;
use App\Bot\Bale\MessageProcessor;
use App\Bot\Bale\Support\SongReactionPresenter;
use App\Bot\BotUserService;
use App\Bot\NotificationService;
use App\Jobs\ProcessBaleMusicDownloadJob;
use App\Models\Song;
use App\Models\SongRequest;
use App\Services\AdminNotifier;
use App\Services\SongReactionService;
use App\Services\SongUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BaleActionService
{
    public function __construct(
        private BaleService $baleService,
        private SongUploadService $songUploadService,
        private BaleShowDownloadOptionsAction $showDownloadOptionsAction,
        private MessageProcessor $messageProcessor,
        private BaleMembershipService $membershipService,
        private NotificationService $notifier,
        private BotUserService $botUserService,
        private BaleAdminService $adminService,
        private SongReactionService $reactionService,
    ) {}



    // ─────────────────────────────────────────────────
    // پیام‌های متنی
    // ─────────────────────────────────────────────────

    public function handleMessage(Request $request): void
    {
        $message  = $request->input('message');
        $chat     = $message['chat'] ?? [];
        $chatType = $chat['type'] ?? 'private';

        if ($chatType !== 'private') {
            return;
        }

        $userId   = $message['from']['id'];
        $userName = $message['from']['first_name'] ?? $message['from']['username'] ?? 'دوست عزیز';
        $chatId   = $chat['id'];

        try {
            $this->botUserService->registerFromMessage($message, 'bale');
            if (!$this->adminService->isAdmin((int) $userId)) {
                $this->notifier->messageReceived($message, 'bale');
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to register/notify bale user', ['error' => $e->getMessage()]);
        }

        if ($this->adminService->isAdmin((int) $userId)) {
            if ($this->adminService->hasActiveFlow((int) $userId)) {
                $this->adminService->handleMessage($message);
                return;
            }

            if ($this->adminService->tryStartCommand($message)) {
                return;
            }
        }

        // 🚀 کاربر از سایت اومده (/start dl_123) → بدون چک عضویت، همین الان آهنگ رو بفرست
        if ($songId = $this->extractDirectDownloadSongId($message)) {
            ProcessBaleMusicDownloadJob::dispatch($songId, $chatId, null, null)
                ->onQueue('download-music');
            return;
        }

        if ($this->isMediaMessage($message)) {
            $this->handleMediaMessage($message);
            return;
        }

        if ($this->isInstaUrl($message)) {
            $this->handleInstaUrl($message);
            return;
        }

        $missing = $this->membershipService->getMissingChannels($userId);

        if (empty($missing)) {
            $this->forgetPendingMessage($userId);
            $this->forgetLastJoinMessage($userId);
            $this->messageProcessor->process($message);
            return;
        }

        $this->cachePendingMessage($userId, $message);
        $this->deleteLastJoinMessage($chatId, $userId);

        $newMessageId = $this->membershipService->sendJoinMessage($chatId, $missing, $userName);

        if ($newMessageId) {
            $this->rememberLastJoinMessage($userId, $newMessageId);
        }
    }

    private function extractDirectDownloadSongId(array $message): ?int
    {
        $text = trim($message['text'] ?? '');

        if (!preg_match('/^\/start\s+dl_(\d+)\s*$/u', $text, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    private function isInstaUrl(array $message): bool
    {
        $text = $this->getMessageText($message);

        if ($text === '') {
            return false;
        }

        return preg_match(
            '~(?:https?:\/\/)?(?:www\.)?(?:m\.)?(?:instagram\.com|instagr\.am)\/[^\s]+~iu',
            $text
        ) === 1;
    }

    private function handleInstaUrl(array $message): void
    {
        $chatId = $message['chat']['id'];
        $this->baleService->sendMessage(
            $chatId,
            "✅ لینک اینستاگرام با موفقیت ثبت شد\n\n"
                . "⏰ آهنگ در صف دانلود قرار گرفت.\n\n"
                . "💎🫡 وقتی آماده شد، همینجا برات ارسال میکنم!",
        );
    }

    private function getMessageText(array $message): string
    {
        return trim(
            $message['text']
                ?? $message['caption']
                ?? ''
        );
    }

    /**
     * بررسی اینکه آیا پیام حاوی مدیا (عکس، ویدیو، موزیک و...) است یا خیر
     */
    private function isMediaMessage(array $message): bool
    {
        return isset($message['photo'])
            || isset($message['video'])
            || isset($message['audio'])
            || isset($message['voice'])
            || isset($message['video_note'])
            || isset($message['document']);
    }

    /**
     * مدیریت و کپی/فوروارد کردن مدیا
     */
    private function handleMediaMessage(array $message): void
    {
        $chatId    = $message['chat']['id'];
        $messageId = $message['message_id'];

        // کپی پیام به کانال کش بله (یا از forwardBaleMessage استفاده کنید)
        $this->baleService->forwardMessage($chatId, $messageId);
        $this->baleService->sendMessage(
            $chatId,
            "✅ فایلی که فرستادی با موفقیت ثبت شد\n\n"
                . "آهنگ توی صف دانلود قرار گرفت 🔎\n\n"
                . "💎🫡 وقتی آماده شد، همینجا برات ارسال میکنم!",
        );
    }

    // ─────────────────────────────────────────────────
    // کلیک روی دکمه‌ها
    // ─────────────────────────────────────────────────

    public function handleCallback(Request $request): void
    {
        $callback   = $request->input('callback_query');
        $userId     = $callback['from']['id'];
        $userName   = $callback['from']['first_name'] ?? $callback['from']['username'] ?? 'دوست عزیز';
        $chatId     = $callback['message']['chat']['id'];
        $messageId  = $callback['message']['message_id'];
        $data       = $callback['data'] ?? '';
        $callbackId = $callback['id'];

        if (
            $this->adminService->isAdmin((int) $userId)
            && ($data === 'admin_cancel' || $this->adminService->hasActiveFlow((int) $userId))
        ) {
            $this->adminService->handleCallback($callback);
            return;
        }

        // 🚀 لایک/دیس‌لایک نیازی به عضویت کانال نداره
        if (str_starts_with($data, 'react_like_') || str_starts_with($data, 'react_dislike_')) {
            $this->handleSongReactionCallback($callback);
            return;
        }

        // ۱. دکمه "بررسی عضویت"
        if ($data === 'bale_check_membership') {
            $this->handleMembershipCheck($callbackId, $chatId, $messageId, $userId, $userName);
            return;
        }

        // ۲. بقیه دکمه‌ها → اول چک کن عضو هست یا نه
        $missing = $this->membershipService->getMissingChannels($userId);
        if (!empty($missing)) {
            $this->deleteLastJoinMessage($chatId, $userId);
            $newMessageId = $this->membershipService->sendJoinMessage($chatId, $missing, $userName);
            if ($newMessageId) {
                $this->rememberLastJoinMessage($userId, $newMessageId);
            }
            return;
        }

        // ۳. دکمه‌های دانلود آهنگ
        if (str_starts_with($data, 'song_')) {
            $songId = (int) str_replace('song_', '', $data);
            $this->songUploadService->findBestLinkAndSendForBale($callback, $songId, $chatId, $this->baleService);
        } elseif (str_starts_with($data, 'request_song_')) {
            $token = str_replace('request_song_', '', $data);
            $this->handleSongRequest($callbackId, $token, $chatId, $messageId);
        }
    }

    private function handleSongReactionCallback(array $callback): void
    {
        $data      = $callback['data'];
        $chatId    = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $userId    = $callback['from']['id'];

        $type   = str_starts_with($data, 'react_like_') ? 'like' : 'dislike';
        $songId = (int) str_replace(['react_like_', 'react_dislike_'], '', $data);

        $song = Song::find($songId);
        if (!$song) {
            $this->baleService->answerCallbackQuery($callback['id'], '❌ آهنگ پیدا نشد');
            return;
        }

        $result = $this->reactionService->handleReaction($song, $type, 'bale', (string) $userId);

        $toast = match ($result['action']) {
            'added'    => $type === 'like' ? '👍 لایک شد' : '👎 دیس‌لایک شد',
            'switched' => $type === 'like' ? '👍 نظرت به لایک تغییر کرد' : '👎 نظرت به دیس‌لایک تغییر کرد',
            'removed'  => 'نظرت برداشته شد',
        };

        $this->baleService->answerCallbackQuery($callback['id'], $toast);

        $this->baleService->editMessageText(
            $chatId,
            $messageId,
            SongReactionPresenter::messageText(),
            SongReactionPresenter::keyboard($songId, $result['likes_count'], $result['dislikes_count'], $result['user_reaction'])
        );
    }

    // ─────────────────────────────────────────────────
    // بررسی عضویت
    // ─────────────────────────────────────────────────

    private function handleMembershipCheck(
        string $callbackQueryId,
        int|string $chatId,
        int $messageId,
        int $userId,
        string $userName
    ): void {
        $this->baleService->deleteMessage($chatId, $messageId);
        $this->forgetLastJoinMessage($userId);

        $missing = $this->membershipService->getMissingChannels($userId);

        if (empty($missing)) {
            $this->baleService->answerCallbackQuery($callbackQueryId, '✅ عضویت تأیید شد!');
            $pendingMessage = $this->pullPendingMessage($userId);

            if ($pendingMessage) {
                $this->messageProcessor->process($pendingMessage);
            } else {
                $this->membershipService->sendSuccessMessage($chatId, $userName);
            }
        } else {
            $this->baleService->answerCallbackQuery($callbackQueryId);
            $newMessageId = $this->membershipService->sendJoinMessage($chatId, $missing, $userName);
            if ($newMessageId) {
                $this->rememberLastJoinMessage($userId, $newMessageId);
            }
        }
    }

    // ─────────────────────────────────────────────────
    // ثبت درخواست آهنگ
    // ─────────────────────────────────────────────────

    private function handleSongRequest(string $callbackQueryId, string $token, int|string $chatId, int $messageId): void
    {
        $payload = Cache::pull("song_request:{$token}");

        if (!$payload) {
            $this->baleService->answerCallbackQuery($callbackQueryId, '⚠️ درخواست منقضی شده');
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
                'platform'   => 'bale',
            ]);

            $this->baleService->editMessageText(
                $chatId,
                $messageId,
                "✅ پیام شما با موفقیت ثبت شد\n\n"
                    . "🎵 آهنگ در صف دانلود قرار گرفت\n\n"
                    . "💎🫡 وقتی آماده شد، همینجا برات ارسال میکنم!",
                []
            );

            $this->baleService->answerCallbackQuery($callbackQueryId, '✅ درخواست ثبت شد!');

            // ✅ نوتیفیکیشن درخواست آهنگ به ادمین
            $this->notifier->songRequest($payload['query'], $payload['from'], $payload['chat_id'], 'bale');
            app(AdminNotifier::class)->notifyNewSongRequest($request, 'bale');
        } catch (\Throwable $e) {
            Log::error('Failed to save song request', ['error' => $e->getMessage()]);
            $this->notifier->error('ثبت درخواست آهنگ (بله)', $e->getMessage(), 'bale');
            $this->baleService->answerCallbackQuery($callbackQueryId, '❌ خطا در ثبت درخواست');
        }
    }

    // ─────────────────────────────────────────────────
    // Cache: پیام در انتظار
    // ─────────────────────────────────────────────────

    private function pendingMessageKey(int $userId): string
    {
        return "bale:pending_message:{$userId}";
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

    // ─────────────────────────────────────────────────
    // Cache: message_id آخرین پیام "عضو شو"
    // ─────────────────────────────────────────────────

    private function lastJoinMessageKey(int $userId): string
    {
        return "bale:last_join_message:{$userId}";
    }

    private function rememberLastJoinMessage(int $userId, int $messageId): void
    {
        $ttl = (int) config('telegram.pending_message_ttl', 10);
        Cache::put($this->lastJoinMessageKey($userId), $messageId, now()->addMinutes($ttl));
    }

    private function deleteLastJoinMessage(int|string $chatId, int $userId): void
    {
        $lastMessageId = Cache::pull($this->lastJoinMessageKey($userId));
        if (!$lastMessageId) {
            return;
        }

        $response = $this->baleService->deleteMessage($chatId, (int) $lastMessageId);
        if (!$response->successful()) {
            Log::debug('Could not delete previous join message', [
                'chat_id'    => $chatId,
                'message_id' => $lastMessageId,
            ]);
        }
    }

    private function forgetLastJoinMessage(int $userId): void
    {
        Cache::forget($this->lastJoinMessageKey($userId));
    }
}
