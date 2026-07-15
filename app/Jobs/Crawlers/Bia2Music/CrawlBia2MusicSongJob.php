<?php

namespace App\Jobs\Crawlers\Bia2Music;

use App\Services\Crawlers\Bia2Music\Bia2MusicCrawlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CrawlBia2MusicSongJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 0;

    public int $timeout = 1200;

    public function __construct(
        public string $sourceUrl,
        public string $title,
        public string $artistName,
    ) {
        $this->onQueue('bia2music-crawler');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('bia2music-song-' . md5($this->sourceUrl)))
                ->expireAfter(1800)
                ->releaseAfter(300),
        ];
    }

    public function handle(Bia2MusicCrawlerService $crawlerService): void
    {
        if ($cooldownSeconds = $crawlerService->getBia2MusicCooldownSeconds()) {
            $releaseSeconds = $cooldownSeconds + random_int(5, 10);

            Log::warning('Bia2Music global cooldown active. Song job released.', [
                'source_url' => $this->sourceUrl,
                'attempt' => $this->attempts(),
                'release_seconds' => $releaseSeconds,
            ]);

            $this->release($releaseSeconds);

            return;
        }

        try {
            Log::info('Bia2Music song job started.', [
                'source_url' => $this->sourceUrl,
                'title' => $this->title,
                'artist_name' => $this->artistName,
                'attempt' => $this->attempts(),
            ]);

            $song = $crawlerService->crawlAndStoreSong(
                sourceUrl: $this->sourceUrl,
                title: $this->title,
                artistName: $this->artistName,
            );

            Log::info('Bia2Music song job finished.', [
                'source_url' => $this->sourceUrl,
                'song_id' => $song?->id,
            ]);
        } catch (Throwable $exception) {
            if ($this->isTemporaryError($exception)) {
                $delay = random_int(5, 10);

                $crawlerService->activateBia2MusicCooldown($delay);

                Log::warning('Bia2Music song temporary error. Global cooldown activated and job released.', [
                    'source_url' => $this->sourceUrl,
                    'title' => $this->title,
                    'artist_name' => $this->artistName,
                    'attempt' => $this->attempts(),
                    'delay_seconds' => $delay,
                    'message' => $exception->getMessage(),
                ]);

                $this->release($delay);

                return;
            }

            throw $exception;
        }
    }

    private function isTemporaryError(Throwable $exception): bool
    {
        do {
            $message = $exception->getMessage();

            if (
                $exception instanceof ConnectionException ||
                str_contains($message, 'cURL error 35') ||
                str_contains($message, 'Connection reset by peer') ||
                str_contains($message, 'cURL error 28') ||
                str_contains($message, 'timed out') ||
                str_contains($message, 'SSL_connect') ||
                str_contains($message, 'Bot Verification') ||
                str_contains($message, 'Verifying that you are not a robot') ||
                str_contains($message, 'Bia2Music bot verification') ||
                str_contains($message, 'Bia2Music song page has no download links') ||
                str_contains($message, 'Bia2Music page has no articles') ||
                str_contains($message, 'Bia2Music request failed') ||
                str_contains($message, 'Bia2Music empty response')
            ) {
                return true;
            }

            $exception = $exception->getPrevious();
        } while ($exception);

        return false;
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Bia2Music song job failed permanently.', [
            'source_url' => $this->sourceUrl,
            'title' => $this->title,
            'artist_name' => $this->artistName,
            'attempt' => $this->attempts(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}
