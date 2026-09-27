<?php

namespace App\Bot\Shared;

interface MessageProcessorInterface
{
    public function process(array $message): void;
}
