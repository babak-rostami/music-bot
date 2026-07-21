<?php

namespace App\Http\Controllers\Admin;

use App\Bot\Bale\Services\BaleService;
use App\Bot\Telegram\Services\TelegramService;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessBaleMusicDownloadJob;
use App\Jobs\ProcessMusicDownloadJob;
use App\Models\BotUser;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BotUserController extends Controller
{
    public function __construct(
        protected TelegramService $telegramService,
        protected BaleService $baleService,
    ) {}

    /**
     * نمایش لیست کاربران ربات همراه با آمار و قابلیت جستجو و فیلتر پیشرفته
     */
    public function index(Request $request): View
    {
        $search = $request->input('search');
        $platform = $request->input('platform');
        $status = $request->input('status');
        $sortBy = $request->input('sort_by', 'last_activity_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $perPage = (int) $request->input('per_page', 15);

        $allowedSorts = ['id', 'user_id', 'first_name', 'last_activity_at', 'created_at', 'failed_send_count'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'last_activity_at';
        }

        $allowedDirections = ['asc', 'desc'];
        if (!in_array($sortDirection, $allowedDirections)) {
            $sortDirection = 'desc';
        }

        $stats = [
            'total' => BotUser::count(),
            'telegram' => BotUser::telegram()->count(),
            'bale' => BotUser::bale()->count(),
            'blocked' => BotUser::blocked()->count(),
            'active_24h' => BotUser::where('last_activity_at', '>=', now()->subHours(24))->count(),
        ];

        $query = BotUser::query()
            ->search($search)
            ->ofPlatform($platform)
            ->blockedStatus($status);

        if ($sortBy === 'last_activity_at') {
            $query->orderByRaw('last_activity_at IS NULL, last_activity_at ' . strtoupper($sortDirection));
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $users = $query->paginate(in_array($perPage, [15, 30, 50, 100]) ? $perPage : 15)
            ->withQueryString();

        return view('admin.bot_users.index', compact('users', 'stats', 'search', 'platform', 'status', 'sortBy', 'sortDirection', 'perPage'));
    }

    /**
     * نمایش جزئیات کامل یک کاربر ربات و فرم ارسال پیام
     */
    public function show(BotUser $botUser): View
    {
        return view('admin.bot_users.show', compact('botUser'));
    }

    /**
     * ارسال پیام متنی یا دستور دانلود آهنگ به کاربر
     */
    public function sendMessage(Request $request, BotUser $botUser): RedirectResponse
    {
        $request->validate([
            'song_id' => 'nullable|integer|min:0',
            'message' => 'nullable|string',
        ]);

        $platform = $botUser->platform;
        $chatId = $botUser->user_id;
        $songId = (int) $request->input('song_id', 0);
        $customMessage = $request->input('message');

        // تعیین متن پیام ارسالی
        // اگر کاربر عدد 0 را وارد کرده باشد (به عنوان string '0' یا integer 0)، هیچ پیام متنی ارسال نمی‌شود
        $shouldSendMessage = true;
        if ($customMessage === '0' || $customMessage === 0) {
            $shouldSendMessage = false;
            $messageText = null;
        } elseif ($customMessage !== null && trim((string)$customMessage) !== '') {
            // اگر پیامی نوشته شده باشد، همان ارسال می‌شود
            $messageText = trim((string)$customMessage);
        } else {
            // اگر فیلد خالی باشد، پیام پیش‌فرض ارسال می‌شود
            $messageText = '❤️آهنگی که میخواستی برات آپلود کردم';
        }

        try {
            if ($platform === BotUser::PLATFORM_TELEGRAM) {
                // ۱. ارسال آهنگ تلگرام (در صورت وارد شدن شناسه آهنگ)
                if ($songId > 0) {
                    ProcessMusicDownloadJob::dispatch(
                        $songId,
                        $chatId,
                        null
                    )->onQueue('download-music');
                }

                // ۲. ارسال پیام متنی تلگرام
                if ($shouldSendMessage && $messageText !== null) {
                    $this->telegramService->sendMessage($chatId, $messageText);
                }
            } elseif ($platform === BotUser::PLATFORM_BALE) {
                // ۱. ارسال آهنگ بله (در صورت وارد شدن شناسه آهنگ)
                if ($songId > 0) {
                    ProcessBaleMusicDownloadJob::dispatch(
                        $songId,
                        $chatId,
                        null
                    )->onQueue('download-music');
                }

                // ۲. ارسال پیام متنی بله
                if ($shouldSendMessage && $messageText !== null) {
                    $this->baleService->sendMessage($chatId, $messageText);
                }
            }

            // آماده‌سازی پیام بازخورد به ادمین
            $feedback = [];
            if ($songId > 0) {
                $feedback[] = "دستور ارسال آهنگ (ID: {$songId} - فقط کیفیت 320) به صف download-music اضافه شد";
            }
            if ($shouldSendMessage && $messageText !== null) {
                $feedback[] = "پیام متنی به کاربر در پلتفرم {$botUser->platform_label} ارسال شد";
            }

            if (empty($feedback)) {
                return redirect()->back()->with('success', 'عملیات بدون ارسال آهنگ یا پیام انجام شد (چون متن روی 0 تنظیم شد و آی‌دی آهنگ وارد نشده بود).');
            }

            return redirect()->back()->with('success', implode(' و ', $feedback) . '.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'خطا در اجرای عملیات ارسال: ' . $e->getMessage());
        }
    }

    /**
     * تغییر وضعیت مسدود بودن (بلاک/آنبلاک) کاربر
     */
    public function toggleBlock(BotUser $botUser): RedirectResponse
    {
        if ($botUser->is_blocked) {
            $botUser->update([
                'is_blocked' => false,
                'blocked_at' => null,
            ]);
            $message = "کاربر {$botUser->full_name} با موفقیت از لیست سیاه خارج (آنبلاک) شد.";
        } else {
            $botUser->update([
                'is_blocked' => true,
                'blocked_at' => now(),
            ]);
            $message = "کاربر {$botUser->full_name} با موفقیت مسدود (بلاک) شد.";
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * صفر کردن تعداد خطاهای ارسال پیام برای کاربر
     */
    public function resetFailedCount(BotUser $botUser): RedirectResponse
    {
        $botUser->update(['failed_send_count' => 0]);

        return redirect()->back()->with('success', "تعداد خطاهای ارسال برای کاربر {$botUser->full_name} به صفر بازنشانی شد.");
    }

    /**
     * خروجی CSV از لیست کاربران با در نظر گرفتن فیلترهای اعمال شده
     */
    public function export(Request $request): StreamedResponse
    {
        $query = BotUser::query()
            ->search($request->input('search'))
            ->ofPlatform($request->input('platform'))
            ->blockedStatus($request->input('status'))
            ->latest();

        $fileName = 'bot_users_' . now()->format('Y_m_d_H_i') . '.csv';

        $headers = [
            'Content-type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename={$fileName}",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'شناسه سیستم',
                'پلتفرم',
                'شناسه پلتفرم (user_id)',
                'نام کاربری',
                'نام',
                'نام خانوادگی',
                'وضعیت بلاک',
                'تعداد خطای ارسال',
                'آخرین فعالیت',
                'تاریخ عضویت',
            ]);

            $query->chunk(500, function ($users) use ($file) {
                foreach ($users as $user) {
                    fputcsv($file, [
                        $user->id,
                        $user->platform_label,
                        $user->user_id,
                        $user->username ?? '-',
                        $user->first_name ?? '-',
                        $user->last_name ?? '-',
                        $user->is_blocked ? 'مسدود' : 'فعال',
                        $user->failed_send_count,
                        $user->last_activity_at ? $user->last_activity_at->format('Y-m-d H:i:s') : '-',
                        $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : '-',
                    ]);
                }
            });

            fclose($file);
        }, 200, $headers);
    }
}
