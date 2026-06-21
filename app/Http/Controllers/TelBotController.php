<?php

namespace App\Http\Controllers;

use App\Services\SongUploadService;
use App\Services\TelegramService;
use App\Telegram\Actions\ShowDownloadOptionsAction;
use App\Telegram\MessageProcessor;
use Illuminate\Http\Request;

class TelBotController extends Controller
{

    private TelegramService $telegramService;
    private SongUploadService $songUploadService;
    private ShowDownloadOptionsAction $showDownloadOptionsAction;
    private MessageProcessor $messageProcessor;

    public function __construct(
        TelegramService $telegramService,
        SongUploadService $songUploadService,
        ShowDownloadOptionsAction $showDownloadOptionsAction,
        MessageProcessor $messageProcessor
    ) {
        $this->telegramService = $telegramService;
        $this->songUploadService = $songUploadService;
        $this->showDownloadOptionsAction = $showDownloadOptionsAction;
        $this->messageProcessor = $messageProcessor;
    }

    public function index(Request $request)
    {
        // وقتی کاربر روی دکمه کلیک کرده
        if ($request->has('callback_query')) {
            $this->handleCallback($request);
        }

        // وقتی پیام معمولی فرستاده
        if ($request->has('message')) {
            $this->messageProcessor->process($request->input('message'));
        }

        return response()->json(['status' => 'ok']);
    }

    private function handleCallback(Request $request)
    {
        $callback = $request->input('callback_query');
        $data = $callback['data'];
        $chatId = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id']; // <-- message_id را اینجا بگیرید

        // توی پیشنهاد ها روی اسم آهنگ کلیک شده
        if (str_starts_with($data, 'song_')) {
            $songId = (int) str_replace('song_', '', $data);
            $this->showDownloadOptionsAction->execute($callback['id'], $songId, $chatId, $messageId);
        }
        // وقتی روی دکمه دانلود کلیک شده
        elseif (str_starts_with($data, 'download_music_')) {
            $this->songUploadService->processAndSend($callback, $data, $chatId, $this->telegramService);
        }
    }
}
