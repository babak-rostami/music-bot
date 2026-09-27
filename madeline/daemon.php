<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;

$sessionDir = __DIR__ . '/../storage/app/private/madeline';

if (!is_dir($sessionDir) && !mkdir($sessionDir, 0775, true) && !is_dir($sessionDir)) {
    throw new RuntimeException("Could not create session directory: {$sessionDir}");
}

$session = $sessionDir . '/session.madeline';

$apiId = getenv('MADELINE_API_ID');
$apiHash = getenv('MADELINE_API_HASH');

if ($apiId === false || $apiHash === false) {
    throw new RuntimeException('MADELINE_API_ID or MADELINE_API_HASH is not set.');
}

$settings = new Settings();
$settings->getAppInfo()->setApiId((int) $apiId);
$settings->getAppInfo()->setApiHash($apiHash);

$MadelineProto = new API($session, $settings);
$MadelineProto->start();

fwrite(STDOUT, "MadelineProto daemon در حال اجراست.\n");

while (true) {
    sleep(3600);
}
