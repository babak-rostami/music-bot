# سرویس MadelineProto — جستجوی پیشرفته‌ی آهنگ (Music Finder)

این مستند دقیقاً توضیح می‌ده چه سیستمی ساختیم، چطوری کار می‌کنه، و چطوری از صفر روی یه سرور جدید راه‌اندازیش کنیم.

---

## ۱. این سرویس چیه و چرا ساختیمش

وقتی کاربر توی بات‌های تلگرام/بله ما آهنگی می‌فرسته که توی دیتابیس خودمون نیست، به‌جای اینکه فقط یه درخواست ثبت کنیم و منتظر بمونیم ادمین دستی آپلودش کنه، یه اکانت **کاربری واقعی تلگرام** (نه بات) با کتابخونه‌ی [MadelineProto](https://docs.madelineproto.xyz) به یکی از چند تا «بات آهنگ‌یاب» عمومی توی تلگرام (مثل `@music_yab_jangi_bot`) پیام می‌ده، جوابشو می‌گیره، و همون آهنگ رو برای کاربر خودمون می‌فرسته — همه‌ی این‌ها خودکار و در عرض چند ثانیه.

چرا اکانت کاربری، نه بات API؟ چون بات‌های آهنگ‌یاب مقصد فقط با کاربرای عادی تعامل دارن (پیام می‌فرستن، دکمه می‌زنن)، نه با بات‌های دیگه.

---

## ۲. معماری کلی

```
کاربر ما → بات ما (تلگرام/بله) → MusicFinderJob (صف) → MusicFinderBotRegistry
                                                              ↓
                                              MusicFinderService (یکی برای هر بات مقصد)
                                                              ↓
                                        MadelineConnection → دیمن MadelineProto (IPC)
                                                              ↓
                                              بات آهنگ‌یاب مقصد (تلگرام)
```

### اجزای اصلی

| فایل | نقش |
|---|---|
| `madeline/login.php` | فقط برای لاگین اولیه (یک‌بار، دستی) |
| `madeline/daemon.php` | پروسه‌ی همیشه‌روشن که سشن رو زنده نگه می‌داره |
| `App\Services\MusicFinder\MadelineConnection.php` | اتصال IPC سریع از داخل کد لاراول به دیمن |
| `App\Services\MusicFinder\MusicFinderService.php` | صحبت با **یک** بات مقصد مشخص (ارسال متن/رسانه/کلیک دکمه، تفسیر جواب) |
| `App\Services\MusicFinder\MusicFinderBotRegistry.php` | نگهداری چند نمونه از `MusicFinderService` + انتخاب یکی‌درمیون (round-robin) |
| `App\Services\MusicFinder\MusicFinderResult.php` | نتیجه‌ی استاندارد (آهنگ / رله / دکمه‌ها / خطا) |
| `App\Services\MusicFinder\MusicFinderAccess.php` | گیت ادمین (حالت تست) + قفل ضدِ‌کلیک‌همزمان هر کاربر |
| `App\Services\MusicFinder\MusicFinderUsageService.php` | ثبت مصرف + محدودیت رایگان (Quota) |
| `App\Jobs\MusicFinderJob.php` | هماهنگ‌کننده: صدا زدن سرویس، تحویل نتیجه به کاربر، نوتیف ادمین، پاکسازی فایل موقت |

### چرا IPC؟

`MadelineProto` وقتی `->start()` صدا زده بشه، یه سرور IPC (روی یه سوکت یونیکس، کنار فایل سشن) راه می‌اندازه. هر اسکریپت دیگه‌ای (حتی از یه پروسه‌ی کاملاً جدا، مثل یه Job لاراول) که همون مسیر فایل سشن رو با `new API($session)` باز کنه، **به همون دیمن وصل می‌شه**، نه اینکه یه سشن جدید بسازه. برای همین لازمه یه پروسه‌ی دیمن همیشه روشن باشه (`madeline/daemon.php`، توی یه کانتینر جدا)، و بقیه‌ی کد فقط بهش وصل می‌شن.

---

## ۳. راه‌اندازی از صفر (روی یه سرور/پروژه‌ی جدید)

### گام ۱ — نصب پکیج
```bash
composer require danog/madelineproto -W
```
⚠️ فلگ `-W` (`--with-all-dependencies`) لازمه چون MadelineProto به نسخه‌ی خاصی از `webmozart/assert` نیاز داره که ممکنه با قفل فعلی `composer.lock` تناقض داشته باشه.

### گام ۲ — اکستنشن‌های PHP
توی `Dockerfile`، بعد از نصب بقیه‌ی اکستنشن‌ها:
```dockerfile
RUN apt-get update && apt-get install -y --no-install-recommends libgmp-dev \
    && docker-php-ext-install gmp \
    && rm -rf /var/lib/apt/lists/*
```
(`mbstring`, `xml`, `json`, `fileinfo` معمولاً از قبل برای خود لاراول نصبن.)

### گام ۳ — گرفتن `api_id` / `api_hash`
۱. برو به [my.telegram.org](https://my.telegram.org) و با شماره‌ای که می‌خوای MadelineProto باهاش لاگین کنه وارد شو.
۲. برو توی **API development tools**.
۳. یه اپلیکیشن بساز (App title و Short name هرچی، Platform: Other).
۴. مقادیر **App api_id** (عدد) و **App api_hash** (رشته‌ی ۳۲ کاراکتری) رو یادداشت کن.

### گام ۴ — کانفیگ

`.env`:
```env
MADELINE_API_ID=12345678
MADELINE_API_HASH=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# یوزرنیم هر بات آهنگ‌یاب مقصد (هر چندتا که می‌خوای، اسم متغیر رو خودت انتخاب کن)
MUSIC_FINDER_BOT_USERNAME=@music_yab_jangi_bot
MUSIC_FINDER_BOT_2_USERNAME=@music_yabi_ma_bot

MUSIC_FINDER_TIMEOUT=40
MUSIC_FINDER_MIN_GAP_SECONDS=3
MUSIC_FINDER_ENABLED_FOR_ALL=false
MUSIC_FINDER_FREE_LIMIT=3
MUSIC_FINDER_FREE_WINDOW_HOURS=12
MUSIC_FINDER_RELAY_CHANNEL_ID=
```

`config/services.php`:
```php
'madeline' => [
    'api_id'   => env('MADELINE_API_ID'),
    'api_hash' => env('MADELINE_API_HASH'),
],

'music_finder_bots' => [
    ['key' => 'primary',   'username' => env('MUSIC_FINDER_BOT_USERNAME')],
    ['key' => 'secondary', 'username' => env('MUSIC_FINDER_BOT_2_USERNAME')],
],

'music_finder_bot' => [
    'timeout'                    => env('MUSIC_FINDER_TIMEOUT', 40),
    'enabled_for_all'            => env('MUSIC_FINDER_ENABLED_FOR_ALL', false),
    'min_gap_seconds'            => env('MUSIC_FINDER_MIN_GAP_SECONDS', 3),
    'free_requests_limit'        => env('MUSIC_FINDER_FREE_LIMIT', 3),
    'free_requests_window_hours' => env('MUSIC_FINDER_FREE_WINDOW_HOURS', 12),
    'relay_channel_id'           => env('MUSIC_FINDER_RELAY_CHANNEL_ID'),
],
```

### گام ۵ — `madeline/login.php`
```php
<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;

$session = __DIR__ . '/../storage/app/private/madeline/session.madeline';

$settings = new Settings;
$settings->getAppInfo()->setApiId((int) getenv('MADELINE_API_ID'));
$settings->getAppInfo()->setApiHash(getenv('MADELINE_API_HASH'));

// لاگ سبک، فقط هشدار/خطا — نه هر اتفاق ریز داخلی
$settings->getLogger()
    ->setType(Logger::FILE_LOGGER)
    ->setExtra(__DIR__ . '/MadelineProto.log')
    ->setLevel(Logger::LEVEL_WARNING)
    ->setMaxSize(20 * 1024 * 1024);

$MadelineProto = new API($session, $settings);
$MadelineProto->start(); // اینجا شماره تلفن + کد پیامکی (و رمز دومرحله‌ای در صورت وجود) می‌پرسه

$me = $MadelineProto->getSelf();
echo "✅ وارد شدی به عنوان: " . ($me['first_name'] ?? '') . " (@" . ($me['username'] ?? '-') . ")\n";
```

اجرا (فقط یک‌بار، تعاملی):
```bash
docker-compose run --rm -it app php madeline/login.php
```
مراحل: شماره تلفن با کد کشور → کد پیامکی/تلگرام → رمز دومرحله‌ای (اگه فعاله). بعدش سشن توی `storage/app/private/madeline/` ذخیره می‌شه و دیگه لازم نیست دوباره لاگین کنی (تا وقتی اکانت از تلگرام خارج/بن نشه).

### گام ۶ — `madeline/daemon.php`
```php
<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;

$session = __DIR__ . '/../storage/app/private/madeline/session.madeline';

$settings = new Settings;
$settings->getAppInfo()->setApiId((int) getenv('MADELINE_API_ID'));
$settings->getAppInfo()->setApiHash(getenv('MADELINE_API_HASH'));

$settings->getLogger()
    ->setType(Logger::FILE_LOGGER)
    ->setExtra(__DIR__ . '/MadelineProto.log')
    ->setLevel(Logger::LEVEL_WARNING)
    ->setMaxSize(20 * 1024 * 1024);

$MadelineProto = new API($session, $settings);
$MadelineProto->start();

fwrite(STDOUT, "MadelineProto daemon در حال اجراست.\n");

while (true) {
    sleep(3600);
}
```

### گام ۷ — سرویس داکر
`docker-compose.yml`:
```yaml
  madeline-daemon:
    image: musicbot-app
    container_name: musicbot-madeline-daemon
    restart: unless-stopped
    init: true
    working_dir: /var/www
    command: php madeline/daemon.php
    volumes:
      - ./:/var/www
    networks:
      - music-network
```
یه صف اختصاصی هم لازمه (بالای فایل، کنار بقیه‌ی `x-...-worker`):
```yaml
x-music-finder-worker: &music-finder-worker
  <<: *queue-worker
  command: >
    php artisan queue:work --queue=music-finder --tries=1 --timeout=90 --max-jobs=200 --max-time=3600
```
و توی `services:`:
```yaml
  queue-music-finder:
    <<: *music-finder-worker
    container_name: musicbot-queue-music-finder
```

بالا آوردن:
```bash
docker-compose up -d madeline-daemon queue-music-finder
```

### گام ۸ — کانال رله (بهینه‌سازی تلگرام، اختیاری ولی خیلی مهم)

توی تلگرام، به‌جای دانلود آهنگ از بات مقصد و آپلود دوباره‌اش برای کاربر (که منابع سرور رو مصرف می‌کنه)، پیام آهنگ مستقیم **سمت سرورهای تلگرام** به یه کانال رله فوروارد می‌شه، بعد با `copyMessage` از همون کانال به کاربر می‌رسه — بدون دانلود/آپلود دستی.

راه‌اندازی:
۱. یه کانال خصوصی جدید بساز (مثلاً «Relay Cache»).
۲. بات API خودت (تلگرام) رو ادمین اون کانال کن.
۳. همون اکانتی که با MadelineProto لاگین کردی رو هم عضو/ادمین همون کانال کن (از اپ تلگرام روی گوشی/دسکتاپ خودت).
۴. شناسه‌ی عددی کانال رو بگیر (چیزی شبیه `-100xxxxxxxxxx`) و بذارش توی `.env`:
```env
MUSIC_FINDER_RELAY_CHANNEL_ID=-100xxxxxxxxxx
```

⚠️ اگه این تنظیم نشه یا فوروارد شکست بخوره، کد خودکار به روش قدیمی (دانلود فایل واقعی) برمی‌گرده — هیچی خراب نمی‌شه، فقط بهینه‌سازی غیرفعال می‌مونه. برای بله همیشه دانلود واقعی انجام می‌شه چون بله یه پلتفرم کاملاً جداست و راهی برای فوروارد مستقیم بین‌شون نیست.

---

## ۴. رفتار سیستم — سه تا ورودی

۱. **متن ساده** → پیام به بات مقصد → لیست دکمه‌ی آهنگ‌های مشابه → کاربر کلیک می‌کنه → همون آهنگ ارسال می‌شه.
۲. **لینک یوتیوب/اینستاگرام/تیک‌تاک** → پیام به بات مقصد → (گاهی نیاز به کلیک دکمه‌ی «شناسایی موسیقی» داره، گاهی خودکار جواب می‌ده — کد هر دو حالت رو تشخیص می‌ده) → آهنگ.
۳. **فایل صوتی/ویدیو/ویس مستقیم** → آپلود به بات مقصد → آهنگ (بدون نیاز به دکمه).

توی هر سه حالت، پیام‌های میانی (استیکر «در حال جستجو»، عکس اطلاعات، متن وضعیت موقت) خودکار نادیده گرفته می‌شن تا پاسخ نهایی واقعی (آهنگ یا خطا) برسه — منطقش توی `MusicFinderService::shouldSkipMessage()` هست.

هر پاسخ متنی نهایی غیرمنتظره (نتونستم پیدا کنم، خطای اینستاگرام، یا هر چیز دیگه) با یه پیام تمیز و ثابت خودمون جایگزین می‌شه، نه متن خام بات مقصد — که هم تبلیغ/لینک اون بات درز نکنه، هم برای کاربر یکدست بمونه.

---

## ۵. محدودیت رایگان (Quota)

هر کاربر در هر ۱۲ ساعت ۳ تا «جستجوی پیشرفته» (لینک/رسانه/دکمه‌ی «جستجوی پیشرفته») رایگان داره. جستجوی متنی ساده‌ی داخل دیتابیس خودمون **نامحدوده** و شمرده نمی‌شه. شمارش فقط وقتی که آهنگ **واقعاً با موفقیت** تحویل داده بشه افزایش پیدا می‌کنه (نه موقع نمایش دکمه، نه موقع خطا). جدول `music_finder_requests` تاریخچه‌ی کامل رو نگه می‌داره؛ پنل ادمین (`admin_page/music-finder-requests`) آمارش رو نشون می‌ده.

---

## ۶. حالت تست (بدون ریسک روی کاربرای واقعی)

تا وقتی `MUSIC_FINDER_ENABLED_FOR_ALL=false` باشه، فقط شناسه‌ی ادمین (`TELEGRAM_ADMIN_ID`/`BALE_ADMIN_ID`) اجازه‌ی استفاده از این فیچر رو داره؛ بقیه‌ی کاربرا رفتار قدیمی (پیام «صف دانلود») رو می‌بینن. برای فعال‌سازی کامل، فقط:
```env
MUSIC_FINDER_ENABLED_FOR_ALL=true
```
و `php artisan config:clear`.

### دستورات تست
```bash
# تست مستقیم سرویس (round-robin بین بات‌ها)، بدون نیاز به وب‌هوک واقعی
php artisan music-finder:test "اسم یه آهنگ"
php artisan music-finder:test "https://www.youtube.com/watch?v=xxxx"
php artisan music-finder:test "/path/to/file.mp3" --media=audio

# تست کلیک روی یکی از دکمه‌ها (botKey از خروجی دستور بالا میاد)
php artisan music-finder:click primary 556353 "track_id:xxxx:yt"

# شبیه‌سازی کامل یه پیام واقعی از تلگرام/بله (از توی خود بات)
php artisan bot:simulate telegram text "حرمسرا تتلو"
php artisan bot:simulate bale link "https://www.instagram.com/..."
```

---

## ۷. مشکلات حل‌شده (برای مرجع آینده)

| مشکل | علت | راه‌حل |
|---|---|---|
| `['botAPI' => true]` جواب رو به فرمت آشنای Bot API تبدیل نمی‌کرد | این پارامتر اون‌جوری که فکر می‌کردیم کار نمی‌کنه؛ پاسخ همیشه فرمت خام MTProT هست | `parseMessage` بر اساس ساختار خام (`message['message']`, `reply_markup.rows[].buttons[]`, `_: keyboardButtonCallback`) بازنویسی شد |
| `callback_data` دکمه‌ها رشته نبود | یه آبجکت از کلاس `Bytes` هست، نه string خام | `extractCallbackData()` بایت خام رو از `->bytes` استخراج می‌کنه |
| کلیک روی دکمه گاهی بعد از آهنگ جواب می‌داد که «خطا»ست | `waitForReply` اولین پیام جدید رو (حتی اگه یه وضعیت موقتی بود) جواب نهایی حساب می‌کرد | `shouldSkipMessage()` پیام‌های میانی (⏳، «در حال»، ویدیو/عکس بدون دکمه، استیکر) رو رد می‌کنه |
| بات دوم (`music_yabi_ma_bot`) آهنگ رو می‌فرستاد ولی کد خطا می‌داد: `BOT_RESPONSE_TIMEOUT` | این بات به کلیک دکمه با `answerCallbackQuery` جواب نمی‌ده؛ تلگرام بعد از چند ثانیه این خطا رو می‌ده، ولی کلیک واقعاً پردازش شده بود | `clickButton()` این خطای خاص رو نادیده می‌گیره و به گوش‌دادن برای جواب ادامه می‌ده |
| قفل سراسری با اضافه‌شدن بات دوم مانع کار موازی می‌شد | قفل و فاصله‌ی زمانی سراسری بودن | قفل و فاصله‌ی زمانی per-bot شدن (`music_finder_lock:{botKey}`) — هر بات مستقل صف داره |

---

## ۸. اضافه کردن یه بات آهنگ‌یاب سوم

فقط دو خط لازمه:
```env
MUSIC_FINDER_BOT_3_USERNAME=@بات_سوم
```
```php
// config/services.php → music_finder_bots
['key' => 'tertiary', 'username' => env('MUSIC_FINDER_BOT_3_USERNAME')],
```
`MusicFinderBotRegistry` خودکار می‌فهمتش و توی چرخه‌ی round-robin قرارش می‌ده.

---

## پیوست: سرویس دانلود یوتیوب (yt-dlp + کوکی)

این بخشیه که به‌طور جدا (نه از طریق MadelineProto) برای دانلود مستقیم آهنگ از لینک یوتیوب/ساندکلاد ساختیم — وقتی `song.youtube_link`/`song.soundcloud_link` توی دیتابیس ثبت شده باشه. جزئیات کامل‌ترش پایین توی پاسخ آورده شده؛ خلاصه‌ی مسیرش:

`SongFileService::downloadBestSong()` → اگه لینک‌های دانلود ذخیره‌شده جواب ندن → `YoutubeDownloadService`/`SoundcloudDownloadService` (هر دو از باینری `yt-dlp` نصب‌شده روی ایمیج استفاده می‌کنن) → از کوکی export‌شده (`YTDLP_COOKIES_PATH`) برای دور زدن چک ربات‌بودن یوتیوب استفاده می‌کنن.

فایل کوکی باید اینجا باشه:
storage/app/private/ytdlp-cookies.txt
و توی .env:
YTDLP_COOKIES_PATH=/var/www/storage/app/private/ytdlp-cookies.txt