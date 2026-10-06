<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('emoji', 10)->nullable();
            $table->unsignedInteger('requests_per_window');
            $table->unsignedInteger('window_hours')->default(12);
            $table->unsignedInteger('monthly_price_toman');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // «سفارش» — دقیقاً چی با چه مشخصاتی انتخاب شده، مستقل از این‌که پولش چطور
        // پرداخت می‌شه. عمداً هیچ ستون status نداره — سرنوشتش کاملاً از روی وضعیت
        // Payment متصل‌بهش (از طریق رابطه‌ی پلی‌مورفیک) تعیین می‌شه؛ دو state machine
        // موازی که ممکنه از هم عقب بیفتن نمی‌خوایم.
        Schema::create('subscription_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);
            $table->unsignedBigInteger('user_id');

            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            // اسنپ‌شات پلن لحظه‌ی انتخاب — حتی اگه پلن اصلی بعداً ویرایش/حذف/غیرفعال بشه، این سفارش دست‌نخورده می‌مونه
            $table->string('plan_name_snapshot');
            $table->unsignedInteger('requests_per_window_snapshot');
            $table->unsignedInteger('window_hours_snapshot');
            $table->unsignedTinyInteger('duration_months');
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->unsignedInteger('price_toman');

            $table->timestamps();

            // دلیل: پیدا کردن «خریدهای یک کاربر» (پنل ادمین + گزارش‌گیری)
            $table->index(['platform', 'user_id'], 'subscription_purchases_user_lookup_index');
        });

        // «تراکنش مالی خالص» — کاملاً مستقل از این‌که چی خریده شده. از طریق
        // payable_type/payable_id به هر نوع خریدی (الان: SubscriptionPurchase، فردا:
        // هر مدل دیگه‌ای) وصل می‌شه، بدون این‌که این جدول خودش چیزی درباره‌ش بدونه.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // دلیل unique+index: این توکنه که توی URL (/pay/{token}) استفاده می‌شه؛
            // هر callback/verify اول با همین پیدا می‌شه. امنیت هم از همینجا میاد:
            // غیرقابل‌حدس، برخلاف id که عدد ترتیبیه.
            $table->string('token', 64)->unique();

            $table->string('platform', 20);
            $table->unsignedBigInteger('user_id');

            // morphs() خودش دو ستون (payable_type, payable_id) + یه ایندکس ترکیبی
            // روشون می‌سازه — برای وقتی که از سمت محصول بخوایم پرداخت‌هاش رو پیدا کنیم.
            $table->morphs('payable');

            $table->string('gateway', 30)->default('zarinpal');
            $table->unsignedInteger('amount_toman');

            // دلیل ایندکس: موقع callback/verify درگاه، گاهی لازمه از روی authority
            // (نه token) هم تراکنش پیدا بشه؛ nullable چون فقط بعد از رسیدن به درگاه پر می‌شه.
            $table->string('authority')->nullable()->index();
            $table->string('ref_id')->nullable();
            $table->string('card_pan')->nullable();

            $table->string('status', 20)->default('created');

            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            // دلیل: پنل ادمین (فیلتر پرداخت‌های یک کاربر بر اساس پلتفرم/وضعیت)
            $table->index(['platform', 'user_id', 'status'], 'payments_user_status_index');

            // دلیل: مهم‌ترین ایندکس برای جاب‌های پس‌زمینه — «همه‌ی pending هایی که
            // ۵ دقیقه تا ۲۴ ساعت پیش آپدیت شدن» (ReconcilePendingPayments) و
            // «created/pending قدیمی‌تر از X ساعت» و «failed/expired قدیمی‌تر از
            // X روز» (CleanupStalePayments) — همه از همین یه ایندکس استفاده می‌کنن.
            $table->index(['status', 'updated_at'], 'payments_status_updated_index');
        });

        // «وضعیت واقعی اشتراک» — تنها جدولی که منطق محدودیت/دسترسی کاربر ازش می‌خونه.
        // عمداً جدا از payments، چون چک‌کردن محدودیت (که روی هر درخواست جستجوی
        // پیشرفته اجرا می‌شه — مسیر داغ سیستمه) نباید نیازی به دونستن جزئیات
        // تراکنش مالی یا join زدن به جدول‌های دیگه داشته باشه.
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);
            $table->unsignedBigInteger('user_id');

            // یونیک بودن همین ستون، idempotency رو در سطح دیتابیس تضمین می‌کنه:
            // هیچ پرداختی نمی‌تونه دوبار اشتراک بسازه، حتی اگه کد اپ باگ داشته باشه.
            $table->foreignId('payment_id')->unique()->constrained('payments')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();

            // اسنپ‌شات تکراری نسبت به subscription_purchases؟ عمداً بله — چون این مسیر
            // (چک محدودیت کاربر) پرتکرارترین کوئری کل سیستمه و نباید برای خوندنش
            // مجبور به دنبال‌کردن زنجیره‌ی subscriptions → payments → payable بشیم.
            $table->string('plan_name_snapshot');
            $table->unsignedInteger('requests_per_window_snapshot');
            $table->unsignedInteger('window_hours_snapshot');
            $table->unsignedTinyInteger('duration_months');

            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->string('status', 20)->default('active');

            $table->timestamps();

            // دلیل: مهم‌ترین و پرتکرارترین کوئری کل سیستم — «آیا این کاربر الان
            // اشتراک فعال داره؟» (MusicFinderUsageService، روی هر درخواست). ترتیب
            // ستون‌ها عمدیه: برابری‌ها (platform, user_id, status) اول، بازه (expires_at) آخر.
            $table->index(['platform', 'user_id', 'status', 'expires_at'], 'subscriptions_active_lookup_index');

            // دلیل: جاب روزانه‌ی پاکسازی که بدون فیلتر کاربر خاص، کل جدول رو برای
            // «اشتراک‌های active ای که زمانشون گذشته» می‌گرده — pattern کاملاً متفاوت
            // از ایندکس بالا (چون platform/user_id رو فیلتر نمی‌کنه)، نیاز به ایندکس جدا داره.
            $table->index(['status', 'expires_at'], 'subscriptions_expiry_cleanup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscription_purchases');
        Schema::dropIfExists('subscription_plans');
    }
};
