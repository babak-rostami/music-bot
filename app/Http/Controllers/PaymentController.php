<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * کنترلر مربوط به صفحات و Callback پرداخت.
 *
 * این کنترلر فقط مسئول دریافت HTTP Request و هدایت جریان پرداخت است
 * و منطق اصلی پرداخت را خودش انجام نمی‌دهد.
 *
 * دو نقطه اصلی در این کنترلر وجود دارد:
 *
 * 1. redirect:
 *    وقتی کاربر روی لینک پرداخت کلیک می‌کند، Payment را بررسی کرده
 *    و در صورت معتبر بودن، کاربر را به درگاه پرداخت می‌فرستد.
 *
 * 2. callback:
 *    بعد از اتمام یا لغو پرداخت، درگاه کاربر را به این متد برمی‌گرداند.
 *    اینجا اطلاعات برگشتی بررسی شده و برای تأیید نهایی پرداخت،
 *    PaymentService فراخوانی می‌شود.
 *
 * PaymentController تصمیم‌های مربوط به پرداخت را به PaymentService
 * واگذار می‌کند تا منطق پرداخت در Controller پخش نشود.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    /**
     * کاربر را برای انجام پرداخت به درگاه هدایت می‌کند.
     *
     * ابتدا Payment با Token پیدا می‌شود و وضعیت آن بررسی می‌شود:
     *
     * - اگر قبلاً Paid شده باشد، کاربر نباید دوباره همان پرداخت را انجام دهد.
     * - اگر Failed یا Expired باشد، پرداخت دیگر قابل استفاده نیست.
     * - در غیر این صورت، PaymentService فرآیند ارسال Payment به درگاه را انجام می‌دهد.
     *
     * این متد خودش با API درگاه ارتباط برقرار نمی‌کند؛
     * فقط نتیجه PaymentService را می‌گیرد و مرورگر را به آدرس درگاه هدایت می‌کند.
     *
     * اگر هنگام ایجاد یا آماده‌سازی پرداخت خطایی رخ دهد،
     * خطا در لاگ ثبت شده و صفحه خطای مناسب به کاربر نمایش داده می‌شود.
     */
    public function redirect(string $token)
    {
        $payment = Payment::where('token', $token)->firstOrFail();

        if ($payment->status === PaymentStatus::Paid) {
            return view('payment.already-paid', compact('payment'));
        }

        if (in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::Expired], true)) {
            return view('payment.expired', compact('payment'));
        }

        try {
            $url = $this->paymentService->redirectToGateway($payment);
            return redirect()->away($url);
        } catch (\Throwable $e) {
            Log::error('Payment redirect failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            return view('payment.error', compact('payment'));
        }
    }
    /**
     * Callback پرداخت را از درگاه دریافت و پرداخت را برای تأیید ارسال می‌کند.
     *
     * بعد از اینکه کاربر از صفحه درگاه خارج شد، زرین‌پال او را به این
     * آدرس برمی‌گرداند و اطلاعات نتیجه پرداخت را در Query String ارسال می‌کند.
     *
     * ابتدا Status بررسی می‌شود:
     * اگر OK نباشد، یعنی پرداخت از دید درگاه موفق نبوده یا کاربر آن را لغو کرده
     * است؛ بنابراین پرداخت Pending به Failed تغییر داده شده و صفحه لغو نمایش داده می‌شود.
     *
     * اگر Status برابر OK باشد، Authority برگشتی با Authority ذخیره‌شده
     * برای همان Payment مقایسه می‌شود. این بررسی باعث می‌شود نتوانیم یک
     * Authority متعلق به Payment دیگری را به این Payment نسبت دهیم.
     *
     * در نهایت PaymentService مسئول Verify واقعی پرداخت با درگاه می‌شود.
     * کنترلر فقط نتیجه Verify را دریافت کرده و صفحه مناسب را نمایش می‌دهد.
     */
    public function callback(Request $request, string $token)
    {
        $payment = Payment::where('token', $token)->firstOrFail();

        if ($request->query('Status') !== 'OK') {
            if ($payment->status === PaymentStatus::Pending) {
                $payment->update(['status' => PaymentStatus::Failed]);
            }
            return view('payment.canceled', compact('payment'));
        }

        // Authority برگشتی باید با Authority مربوط به همین Payment یکی باشد.
        // اختلاف آن یعنی اطلاعات Callback با Payment مورد انتظار ما مطابقت ندارد؛
        // بنابراین برای جلوگیری از Verify اشتباه، درخواست را رد می‌کنیم.
        $authority = $request->query('Authority');
        if ($authority && $payment->authority && $authority !== $payment->authority) {
            Log::warning('Authority mismatch on payment callback', ['payment_id' => $payment->id]);
            abort(400);
        }

        // تأیید واقعی پرداخت، بررسی وضعیت در دیتابیس، Lock و فعال‌سازی محصول
        // داخل PaymentService انجام می‌شود. کنترلر فقط نتیجه نهایی را دریافت می‌کند.
        $success = $this->paymentService->verify($payment);
        // PaymentService در صورت موفقیت وضعیت Payment را در دیتابیس تغییر می‌دهد.
        // مدل فعلی کنترلر ممکن است هنوز وضعیت قبلی را داشته باشد، بنابراین
        // آخرین وضعیت پرداخت را دوباره از دیتابیس می‌خوانیم.
        $payment->refresh();

        return $success
            ? view('payment.success', compact('payment'))
            : view('payment.error', compact('payment'));
    }
}
