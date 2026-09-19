<?php

namespace App\Services\Downloaders;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SoundcloudDownloadService
{
    public function download(string $url, string $tempDir): ?string
    {
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $outputTemplate = $tempDir . '/' . Str::uuid() . '.%(ext)s';

        $command = sprintf(
            'yt-dlp -x --audio-format mp3 --audio-quality 0 --no-playlist %s -o %s %s 2>&1',
            YtDlpCommandBuilder::commonArgs(),
            escapeshellarg($outputTemplate),
            escapeshellarg($url)
        );

        exec($command, $lines, $exit);

        if ($exit !== 0) {
            Log::warning('yt-dlp (SoundCloud) download failed', [
                'url'    => $url,
                'output' => $lines,
            ]);
            return null;
        }

        return $this->findDownloadedMp3($tempDir);
    }

    protected function findDownloadedMp3(string $tempDir): ?string
    {
        $files = glob("{$tempDir}/*.mp3");
        return $files[0] ?? null;
    }
}
