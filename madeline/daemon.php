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
