<?php

namespace App\Http\Controllers;

use App\Models\Song;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Str;

class TelBotController extends Controller
{

    public function index(Request $request)
    {
        // وقتی کاربر روی دکمه کلیک کرده
        if ($request->has('callback_query')) {
            return $this->handleCallback($request);
        }

        // وقتی پیام معمولی فرستاده
        if ($request->has('message')) {
            return $this->handleMessage($request);
        }
    }
    private function handleMessage(Request $request)
    {
        $text = $request->input('message.text');
        $chatId = $request->input('message.chat.id');

        $result = Song::searchElastic($text);

        $buttons = [];

        foreach ($result as $song) {
            $buttons[] = [
                [
                    'text' => $song->title . ' - ' . $song->artist_name,
                    'callback_data' => 'song_' . $song->id,
                ]
            ];
        }

        Http::post(env('TELEGRAM_API_URL') . '/sendMessage', [
            'chat_id' => $chatId,
            'text' => 'کدوم یکی رو میخوای برات بفرستم؟',
            'reply_markup' => json_encode([
                'inline_keyboard' => $buttons
            ])
        ]);
    }

    private function handleCallback(Request $request)
    {
        $callback = $request->input('callback_query');
        $data = $callback['data'];
        $chatId = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];

        // با song_ شروع شده یعنی کاربر روی یکی از آهنگ ها کلیک کرده
        if (str_starts_with($data, 'song_')) {
            return $this->handleSongCallback($callback, $data, $chatId);
        }
        // با download_music_ شروع شده یعنی کاربر روی دکمه دانلود کلیک کرده
        elseif (str_starts_with($data, 'download_music_')) {
            return $this->handleDownloadSongCallback($callback, $data, $chatId);
        }
    }
    private function handleDownloadSongCallback($callback, $data, $chatId): void
    {
        [$quality, $song] = $this->extractSongData($data);

        $this->answerCallbackQuery($callback['id']);

        $downloadUrl = $this->getDownloadUrl($song, $quality);
        $fileName = $this->generateFileName($song);

        $tempDirectory = 'temp/' . str()->uuid();

        Storage::disk('local')->makeDirectory($tempDirectory);

        try {

            $originalFile = $this->downloadSong(
                $downloadUrl,
                $tempDirectory
            );

            $cleanFile = $this->removeMetadata(
                $originalFile,
                $tempDirectory,
                $fileName
            );

            $this->sendAudioToTelegram(
                $chatId,
                $song,
                $cleanFile,
                $fileName
            );
        } finally {

            Storage::disk('local')
                ->deleteDirectory($tempDirectory);
        }
    }

    private function extractSongData(string $data): array
    {
        $parts = explode('_', $data);

        return [
            $parts[2],
            Song::findOrFail($parts[3]),
        ];
    }

    private function answerCallbackQuery(string $callbackId): void
    {
        Http::post(
            env('TELEGRAM_API_URL') . '/answerCallbackQuery',
            [
                'callback_query_id' => $callbackId,
            ]
        );
    }

    private function getDownloadUrl(Song $song, string $quality): string
    {
        return $quality === '128'
            ? $song->download_link_128
            : $song->download_link_320;
    }

    private function generateFileName(Song $song): string
    {
        return preg_replace(
            '/[\/\\\\:*?"<>|]/u',
            '',
            "{$song->title} - {$song->artist_name}"
        ) . '.mp3';
    }

    private function downloadSong(
        string $downloadUrl,
        string $tempDirectory
    ): string {

        $filePath = storage_path(
            "app/private/{$tempDirectory}/original.mp3"
        );

        $stream = fopen($filePath, 'w');

        Http::timeout(300)
            ->withOptions([
                'sink' => $stream,
            ])
            ->get($downloadUrl);

        fclose($stream);

        return $filePath;
    }

    private function removeMetadata(
        string $originalFile,
        string $tempDirectory,
        string $fileName
    ): string {

        $cleanFile = storage_path(
            "app/private/{$tempDirectory}/{$fileName}"
        );

        exec(
            "ffmpeg -i "
                . escapeshellarg($originalFile)
                . " -map 0:a -c copy -map_metadata -1 "
                . escapeshellarg($cleanFile)
                . " -y"
        );

        return $cleanFile;
    }

    private function sendAudioToTelegram(
        int|string $chatId,
        Song $song,
        string $filePath,
        string $fileName
    ): void {

        Http::attach(
            'audio',
            fopen($filePath, 'r'),
            $fileName
        )->post(
            env('TELEGRAM_API_URL') . '/sendAudio',
            [
                'chat_id'   => $chatId,
                'title'     => $song->title,
                'performer' => $song->artist_name,
            ]
        );
    }

    private function handleSongCallback($callback, $data, $chatId)
    {
        // استخراج id آهنگ
        $songId = str_replace('song_', '', $data);

        $song = Song::find($songId);

        // مهم: باید جواب کال بک بدی (لودینگ تلگرام قطع نشه)
        Http::post(env('TELEGRAM_API_URL') . '/answerCallbackQuery', [
            'callback_query_id' => $callback['id'],
        ]);

        $buttons[] = [
            [
                'text' => 'دانلود با کیفیت 128',
                'callback_data' => 'download_music_128_' . $song->id,
            ],
            [
                'text' => 'دانلود با کیفیت 320',
                'callback_data' => 'download_music_320_' . $song->id,
            ]
        ];

        Http::post(env('TELEGRAM_API_URL') . '/sendMessage', [
            'chat_id' => $chatId,
            'text' => "🎧 " . $song->title . "\n👤 " . $song->artist_name,
            'reply_markup' => json_encode([
                'inline_keyboard' => $buttons
            ])
        ]);
    }
}
