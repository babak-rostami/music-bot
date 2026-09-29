<?php

namespace App\Http\Controllers;

use App\Bot\Bale\Services\BaleActionService;
use App\Bot\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BaleBotController extends Controller
{
    public function __construct(
        private NotificationService $notifier,
        private BaleActionService $baleActionService,
    ) {}

    public function index(Request $request)
    {
        try {
            // اگه روی دکمه ای کلیک کرده
            if ($request->has('callback_query')) {
                $this->baleActionService->handleCallback($request);
            } elseif ($request->has('message')) {
                // اگه پیام فرستاده - متن، لینک، ویدیو و صوت و ...
                $this->baleActionService->handleMessage($request);
            }
        } catch (\Throwable $e) {
            $this->notifier->error('وبهوک بله', $e->getMessage(), 'bale');
            Log::error('Bale webhook error', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'ok']);
    }
}
