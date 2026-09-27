<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;

$session = __DIR__ . '/../storage/app/private/madeline/session.madeline';

$settings = new Settings;
$settings->getAppInfo()->setApiId((int) getenv('MADELINE_API_ID'));
$settings->getAppInfo()->setApiHash(getenv('MADELINE_API_HASH'));

$MadelineProto = new API($session, $settings);
$MadelineProto->start(); // همینجا شماره تلفن + کد پیامکی (و رمز دومرحله‌ای در صورت وجود) رو می‌پرسه

$me = $MadelineProto->getSelf();
echo "✅ وارد شدی به عنوان: " . ($me['first_name'] ?? '') . " (@" . ($me['username'] ?? '-') . ")\n";
