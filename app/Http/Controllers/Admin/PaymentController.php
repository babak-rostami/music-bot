<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\BotUser;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;

/**
 * کنترلر مدیریت پرداخت‌ها در پنل ادمین.
 *
 * این کنترلر برای مشاهده و فیلتر پرداخت‌ها و همچنین بررسی دستی
 * وضعیت یک پرداخت استفاده می‌شود.
 *
 * منطق اصلی تأیید پرداخت داخل PaymentService قرار دارد و این کنترلر
 * فقط درخواست ادمین را دریافت کرده و نتیجه را نمایش می‌دهد.
 */
class PaymentController extends Controller
{
    /**
     * لیست پرداخت‌ها را با امکان فیلتر نمایش می‌دهد.
     *
     * پرداخت‌ها بر اساس پلتفرم، وضعیت و کاربر قابل فیلتر هستند.
     * علاوه بر لیست پرداخت‌ها، آمار کلی مانند درآمد کل، درآمد امروز،
     * تعداد پرداخت‌های موفق و تعداد پرداخت‌های در انتظار نیز محاسبه می‌شود.
     *
     * اطلاعات کاربرات بات مربوط به پرداخت‌های صفحه نیز دریافت می‌شوند
     * تا در پنل ادمین امکان ورود به صفحه مدیریت کاربر وجود داشته باشد.
     */
    public function index(Request $request)
    {
        $query = Payment::query()->with('payable')->latest();

        if ($platform = $request->input('platform')) {
            $query->where('platform', $platform);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        $payments = $query->paginate(30)->withQueryString();

        // BotUser های مربوط به پرداخت‌های همین صفحه را برای ساخت لینک
        // به صفحه مدیریت کاربر دریافت می‌کنیم.
        // کلید ترکیبی platform:user_id استفاده می‌شود چون user_id به‌تنهایی
        // بین پلتفرم‌های مختلف الزاماً یکتا نیست.
        $botUsers = BotUser::query()
            ->whereIn('platform', $payments->pluck('platform')->unique())
            ->whereIn('user_id', $payments->pluck('user_id')->unique())
            ->get()
            ->keyBy(fn($u) => $u->platform . ':' . $u->user_id);
        $payments->each(function ($payment) use ($botUsers) {
            $payment->setRelation(
                'botUser',
                $botUsers->get($payment->platform . ':' . $payment->user_id)
            );
        });

        // آمار کلی پرداخت‌ها برای نمایش در بالای صفحه مدیریت.
        $stats = [
            'total_revenue' => Payment::where('status', PaymentStatus::Paid->value)->sum('amount_toman'),
            'paid_count'    => Payment::where('status', PaymentStatus::Paid->value)->count(),
            'pending_count' => Payment::whereIn('status', [PaymentStatus::Created->value, PaymentStatus::Pending->value])->count(),
            'today_revenue' => Payment::where('status', PaymentStatus::Paid->value)->whereDate('paid_at', today())->sum('amount_toman'),
        ];

        return view('admin.payments.index', compact('payments', 'botUsers', 'stats', 'platform', 'status', 'userId'));
    }

    /**
     * وضعیت یک پرداخت را به‌صورت دستی بررسی می‌کند.
     *
     * این متد زمانی استفاده می‌شود که ادمین بخواهد بدون انتظار برای
     * پردازش خودکار، وضعیت یک Payment را همان لحظه از درگاه استعلام کند.
     *
     * منطق Verify، جلوگیری از پردازش هم‌زمان و فعال‌سازی محصول
     * همگی داخل PaymentService انجام می‌شوند؛ بنابراین این کنترلر
     * مستقیماً با درگاه پرداخت ارتباط برقرار نمی‌کند.
     */
    public function verifyNow(Payment $payment, PaymentService $paymentService)
    {
        $success = $paymentService->verify($payment);

        return back()->with($success ? 'success' : 'error', $success ? 'پرداخت تأیید شد.' : 'هنوز تأیید نشده (ممکنه واقعاً پرداخت نشده باشه).');
    }
}
