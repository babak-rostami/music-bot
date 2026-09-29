<?php

declare(strict_types=1);

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
