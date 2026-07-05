<?php

namespace App\Http\Controllers;

use App\Services\BaleMembershipService;
use App\Services\BaleService;
use App\Services\SongUploadService;
use App\BaleBot\Actions\BaleShowDownloadOptionsAction;
use App\BaleBot\MessageProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BaleBotController extends Controller
{
    private BaleService $baleService;
    private SongUploadService $songUploadService;
    private BaleShowDownloadOptionsAction $showDownloadOptionsAction;
    private MessageProcessor $messageProcessor;
    private BaleMembershipService $membershipService;

    public function __construct(
        BaleService $baleService,
        SongUploadService $songUploadService,
        BaleShowDownloadOptionsAction $showDownloadOptionsAction,
        MessageProcessor $messageProcessor,
        BaleMembershipService $membershipService
    ) {
        $this->baleService = $baleService;
        $this->songUploadService = $songUploadService;
        $this->showDownloadOptionsAction = $showDownloadOptionsAction;
        $this->messageProcessor = $messageProcessor;
        $this->membershipService = $membershipService;
    }

    /**
     * وبهوک بله
     * Route::post('bale-webhook', [BaleBotController::class, 'index']);
     */
    public function index(Request $request)
    {
        $payload = $request->all();

        // ۱. بررسی callback (کلیک روی دکمه)
        if (isset($payload['callback_query'])) {
            $this->handleCallback($request);
            return response()->json(['status' => 'ok']);
        }

        // ۲. بررسی پیام جدید
        if (isset($payload['message'])) {
            $this->handleMessage($request);
        }

        return response()->json(['status' => 'ok']);
    }

    // ─────────────────────────────────────────────────
    // پیام‌های متنی
    // ─────────────────────────────────────────────────

    private function handleMessage(Request $request): void
    {
        $message  = $request->input('message');
        $chat     = $message['chat'] ?? [];
        $chatType = $chat['type'] ?? 'private';

        // 🔒 نادیده گرفتن پیام‌های کانال و گروه
        if ($chatType !== 'private') {
            return;
        }

        $userId   = $message['from']['id'];
        $userName = $message['from']['first_name'] ?? $message['from']['username'] ?? 'دوست عزیز';
        $chatId   = $chat['id'];

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

    // ─────────────────────────────────────────────────
    // کلیک روی دکمه‌ها
    // ─────────────────────────────────────────────────

    private function handleCallback(Request $request): void
    {
        $callback  = $request->input('callback_query');
        $userId    = $callback['from']['id'];
        $userName  = $callback['from']['first_name'] ?? $callback['from']['username'] ?? 'دوست عزیز';
        $chatId    = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $data      = $callback['data'] ?? '';
        $callbackId = $callback['id']; // شناسه callback برای answer فوری

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
            $this->showDownloadOptionsAction->execute($callbackId, $songId, $chatId, $messageId);
        } elseif (str_starts_with($data, 'download_music_')) {
            // ✅ فوری: answer callback + حذف دکمه‌ها + قفل کردن
            $this->handleMusicDownload($callbackId, $callback, $data, $chatId, $messageId);
        } elseif (str_starts_with($data, 'request_song_')) {
            $token = str_replace('request_song_', '', $data);
            $this->handleSongRequest($callbackId, $token, $chatId, $messageId);
        }
    }

    // ─────────────────────────────────────────────────
    // هندل دانلود آهنگ - با قفل ضد تکرار
    // ─────────────────────────────────────────────────

    private function handleMusicDownload(
        string $callbackId,
        array $callback,
        string $data,
        int|string $chatId,
        int $messageId
    ): void {
        // استخراج song_id از callback_data
        $parts = explode('_', $data);
        $songId = (int) ($parts[3] ?? 0);

        if (!$songId) {
            $this->baleService->answerCallbackQuery($callbackId, '❌ آهنگ پیدا نشد');
            return;
        }

        // ─────────────────────────────────────────────────
        // 🔒 کلید قفل: هر کاربر برای هر آهنگ فقط یکبار
        // ─────────────────────────────────────────────────
        $lockKey = "bale:download_lock:{$chatId}:{$songId}";
        $lockTtl = 30; // ۳۰ ثانیه قفل

        if (Cache::has($lockKey)) {
            // قبلاً در حال پردازش است
            $this->baleService->answerCallbackQuery($callbackId, '⏳ در حال آماده‌سازی...');
            return;
        }

        // ─────────────────────────────────────────────────
        // ✅ قفل رو فعال کن + دکمه‌ها رو حذف کن
        // ─────────────────────────────────────────────────
        Cache::put($lockKey, true, now()->addSeconds($lockTtl));

        // حذف فوری دکمه‌ها و answer callback
        $this->baleService->editMessageText(
            $chatId,
            $messageId,
            '⏳ در حال آماده‌سازی فایل... 🎶',
            []
        );
        $this->baleService->answerCallbackQuery($callbackId);

        // پردازش در background
        $this->songUploadService->processAndSendForBaleWithLock(
            $callback,
            $data,
            $chatId,
            $this->baleService,
            $lockKey
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
            $request = \App\Models\SongRequest::create([
                'user_id'    => $payload['from']['id'],
                'username'   => $payload['from']['username'] ?? null,
                'first_name' => $payload['from']['first_name'] ?? null,
                'last_name'  => $payload['from']['last_name'] ?? null,
                'chat_id'    => $payload['chat_id'],
                'query'      => $payload['query'],
                'status'     => \App\Models\SongRequest::STATUS_PENDING,
            ]);

            $this->baleService->editMessageText(
                $chatId,
                $messageId,
                "✅ درخواست شما ثبت شد!\n\n"
                    . "🎵 آهنگ در لیست جستجو قرار گرفت\n\n"
                    . "⏰ تا چند ساعت آینده برات آپلود میشه.\n\n"
                    . "💎 وقتی آماده شد، همینجا خبرت می‌کنم!",
                []
            );

            $this->baleService->answerCallbackQuery($callbackQueryId, '✅ درخواست ثبت شد!');
            app(\App\Services\AdminNotifier::class)->notifyNewSongRequest($request);
        } catch (\Throwable $e) {
            Log::error('Failed to save song request', ['error' => $e->getMessage()]);
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
        if (!$lastMessageId) return;

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
