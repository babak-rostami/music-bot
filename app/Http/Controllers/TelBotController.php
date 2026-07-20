<?php

namespace App\Http\Controllers;

use App\Bot\NotificationService;
use App\Bot\Telegram\Services\TelegramActionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelBotController extends Controller
{
    public function __construct(
        protected NotificationService $notifier,
        protected TelegramActionService $telegramActionService,
    ) {}

    public function index(Request $request)
    {
        try {
            if ($request->has('callback_query')) {
                $this->telegramActionService->handleCallback($request);
            } elseif ($request->has('message')) {
                $this->telegramActionService->handleMessage($request);
            }
        } catch (\Throwable $e) {
            $this->notifier->error('وبهوک تلگرام', $e->getMessage(), 'telegram');
            Log::error('Telegram webhook error', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'ok']);
    }
}
