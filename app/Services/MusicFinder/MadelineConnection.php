<?php

namespace App\Services\MusicFinder;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;

class MadelineConnection
{
    public static function get(): API
    {
        $session = storage_path('app/private/madeline/session.madeline');

        $settings = new Settings;
        $settings->getAppInfo()->setApiId((int) config('services.madeline.api_id'));
        $settings->getAppInfo()->setApiHash(config('services.madeline.api_hash'));

        $madeline = new API($session, $settings);
        $madeline->start(); // دیمن از قبل روشنه، این فقط یه اتصال IPC سریعه

        return $madeline;
    }
}
