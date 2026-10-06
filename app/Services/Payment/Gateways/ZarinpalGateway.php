<?php

namespace App\Services\Payment\Gateways;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\DTO\PaymentRequestResult;
use App\Services\Payment\DTO\PaymentVerifyResult;
use Illuminate\Support\Facades\Log;
use ZarinPal\Sdk\ClientBuilder;
use ZarinPal\Sdk\Endpoint\PaymentGateway\RequestTypes\RequestRequest;
use ZarinPal\Sdk\Endpoint\PaymentGateway\RequestTypes\VerifyRequest;
use ZarinPal\Sdk\Options;
use ZarinPal\Sdk\ZarinPal;

/**
 * پیاده‌سازی درگاه پرداخت زرین‌پال.
 *
 * این کلاس مسئول ارتباط مستقیم با SDK زرین‌پال است و
 * پاسخ‌های زرین‌پال را به فرمت های استاندارد سیستم تبدیل می‌کند.
 * با DTO هایی که درست کردیم
 * به این شکل، بخش‌های دیگر برنامه نیازی ندارند با جزئیات و پاسخ‌های مخصوص زرین‌پال آشنا باشند.
 * 
 * Authority شناسه‌ای است که زرین‌پال هنگام ساخت درخواست پرداخت می‌دهد
 * شناسه درخواست پرداخت - شماره پیگیری موقت درخواست پرداخت
 * 
 * ref_id کد پیگیری پرداخت
 * شناسه تراکنش تأییدشده
 */
class ZarinpalGateway implements PaymentGatewayInterface
{
    public function key(): string
    {
        return 'zarinpal';
    }

    /**
     * یک نمونه از درگاه پرداخت زرین‌پال را با تنظیمات پروژه ایجاد می‌کند.
     */
    private function gateway()
    {
        $options = new Options([
            'client_builder' => new ClientBuilder(),
            'merchant_id'    => config('services.zarinpal.merchant_id'),
            'sandbox'        => (bool) config('services.zarinpal.sandbox'),
        ]);

        return (new ZarinPal($options))->paymentGateway();
    }

    /**
     * یک درخواست پرداخت در زرین‌پال ایجاد می‌کند.
     *
     * در صورت موفقیت، authority و آدرس انتقال کاربر به درگاه
     * در PaymentRequestResult برگردانده می‌شود.
     */
    public function requestPayment(
        int $amountToman,
        string $description,
        string $callbackUrl,
        ?string $mobile = null
    ): PaymentRequestResult {
        try {
            $gateway = $this->gateway();

            $request = new RequestRequest();
            $request->amount = $amountToman;
            $request->currency = 'IRT'; // مبلغ به تومان است، نه ریال
            $request->description = $description;
            $request->callback_url = $callbackUrl;

            if ($mobile) {
                $request->mobile = $mobile;
            }

            $response = $gateway->request($request);

            if (empty($response->authority)) {
                return new PaymentRequestResult(
                    success: false,
                    errorMessage: 'پاسخ نامعتبر از درگاه پرداخت'
                );
            }

            return new PaymentRequestResult(
                success: true,
                authority: $response->authority,
                redirectUrl: $gateway->getRedirectUrl($response->authority),
            );
        } catch (\Throwable $e) {
            Log::error('Zarinpal request failed', [
                'error' => $e->getMessage(),
            ]);

            return new PaymentRequestResult(
                success: false,
                errorMessage: $e->getMessage()
            );
        }
    }

    /**
     * پرداخت انجام‌شده را در زرین‌پال تأیید می‌کند.
     *
     * کد ۱۰۰ یعنی پرداخت همین الان تأیید شده است.
     * کد ۱۰۱ یعنی پرداخت قبلاً تأیید شده است.
     * هر دو حالت موفقیت در نظر گرفته می‌شوند.
     */
    public function verifyPayment(
        string $authority,
        int $amountToman
    ): PaymentVerifyResult {
        try {
            $gateway = $this->gateway();

            $verifyRequest = new VerifyRequest();
            $verifyRequest->authority = $authority;
            $verifyRequest->amount = $amountToman;
            $verifyRequest->currency = 'IRT';

            $response = $gateway->verify($verifyRequest);

            if (in_array($response->code, [100, 101], true)) {
                return new PaymentVerifyResult(
                    success: true,
                    refId: (string) ($response->ref_id ?? ''),
                    cardPan: $response->card_pan ?? null,
                    alreadyVerified: $response->code === 101,
                    rawCode: $response->code,
                );
            }

            return new PaymentVerifyResult(
                success: false,
                rawCode: $response->code,
                errorMessage: "کد خطا: {$response->code}"
            );
        } catch (\Throwable $e) {
            Log::error('Zarinpal verify failed', [
                'error' => $e->getMessage(),
            ]);

            return new PaymentVerifyResult(
                success: false,
                errorMessage: $e->getMessage()
            );
        }
    }

    /**
     * آدرس انتقال کاربر به صفحه پرداخت زرین‌پال را برمی‌گرداند.
     */
    public function getRedirectUrl(string $authority): string
    {
        return $this->gateway()->getRedirectUrl($authority);
    }
}
