<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * ─────────────────────────────────────────────────────────────────────
 *  کنترلر مدیریت اعلانات / لاگ فعالیت‌ها
 * ─────────────────────────────────────────────────────────────────────
 *
 *  قابلیت‌ها:
 *  ────────────────────────────────────────────────
 *  • index()   → لیست لاگ‌ها با فیلتر (نوع، پلتفرم، سطح، تاریخ) + جستجو + آمار
 *  • show()    → جزئیات کامل یک لاگ (با داده JSON)
 *  • destroy() → حذف یک لاگ
 *  • clear()   → پاکسازی لاگ‌های قدیمی (با پارامتر روز)
 *  • stats()   → آمار آماده برای داشبورد
 */
class ActivityLogController extends Controller
{
    /**
     * لیست اعلانات با فیلتر و جستجو
     */
    public function index(Request $request)
    {
        $query = ActivityLog::query();

        // ─── فیلتر نوع ───
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // ─── فیلتر پلتفرم ───
        if ($platform = $request->input('platform')) {
            $query->where('platform', $platform);
        }

        // ─── فیلتر سطح ───
        if ($level = $request->input('level')) {
            $query->where('level', $level);
        }

        // ─── جستجو در پیام / شناسه ───
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('message', 'LIKE', "%{$search}%")
                    ->orWhere('user_id', 'LIKE', "%{$search}%")
                    ->orWhere('chat_id', 'LIKE', "%{$search}%");
            });
        }

        // ─── فیلتر تاریخ ───
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query->latest()->paginate(20)->withQueryString();

        // ─── آمار برای کارت‌های بالای صفحه ───
        $stats = $this->getStats();

        // ─── گزینه‌های فیلتر ───
        $typeOptions    = ActivityLog::typeLabels();
        $levelOptions   = ActivityLog::levelLabels();
        $platformOptions = [
            'telegram' => 'تلگرام',
            'bale'     => 'بله',
        ];

        return view('admin.activity-logs.index', compact(
            'logs',
            'stats',
            'typeOptions',
            'levelOptions',
            'platformOptions',
        ));
    }

    /**
     * جزئیات کامل یک لاگ
     */
    public function show(ActivityLog $activityLog)
    {
        $activityLog->load(['botUser']);

        return view('admin.activity-logs.show', compact('activityLog'));
    }

    /**
     * حذف یک لاگ
     */
    public function destroy(ActivityLog $activityLog)
    {
        $activityLog->delete();

        return redirect()
            ->route('admin.activity-logs.index')
            ->with('success', 'اعلان حذف شد.');
    }

    /**
     * پاکسازی لاگ‌های قدیمی‌تر از تعداد روز مشخص
     *
     * استفاده: POST admin/activity-logs/clear {days: 30}
     * اگر days ارسال نشود → همه لاگ‌ها پاک می‌شوند.
     */
    public function clear(Request $request)
    {
        $days = (int) $request->input('days', 0);

        if ($days > 0) {
            $count = ActivityLog::where('created_at', '<', now()->subDays($days))->count();
            ActivityLog::where('created_at', '<', now()->subDays($days))->delete();
            $message = "تعداد {$count} اعلان قدیمی‌تر از {$days} روز پاک شد.";
        } else {
            $count = ActivityLog::count();
            ActivityLog::truncate();
            $message = "همه اعلانات ({$count} مورد) پاک شد.";
        }

        return redirect()
            ->route('admin.activity-logs.index')
            ->with('success', $message);
    }

    /**
     * خروجی آمار به‌صورت JSON (اختیاری، برای داشبورد یا API)
     */
    public function stats()
    {
        return response()->json($this->getStats());
    }

    // ═══════════════════════════════════════════════════
    //  Helper: محاسبه آمار
    // ═══════════════════════════════════════════════════

    private function getStats(): array
    {
        $today = today();

        // تعداد بر اساس نوع (همه / امروز)
        $byType = [];
        foreach (ActivityLog::typeLabels() as $type => $label) {
            $byType[$type] = [
                'label' => $label,
                'icon'  => ActivityLog::typeIcons()[$type],
                'total' => ActivityLog::where('type', $type)->count(),
                'today' => ActivityLog::where('type', $type)->whereDate('created_at', $today)->count(),
            ];
        }

        return [
            'total'        => ActivityLog::count(),
            'today'        => ActivityLog::whereDate('created_at', $today)->count(),
            'errors'       => ActivityLog::where('level', ActivityLog::LEVEL_ERROR)->count(),
            'errors_today' => ActivityLog::where('level', ActivityLog::LEVEL_ERROR)
                ->whereDate('created_at', $today)->count(),
            'by_type'      => $byType,
            'telegram'     => ActivityLog::where('platform', 'telegram')->count(),
            'bale'         => ActivityLog::where('platform', 'bale')->count(),
        ];
    }
}
