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

class CrawlBia2MusicPageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 0;

    public int $timeout = 300;

    public function __construct(
        public int $page
    ) {
        $this->onQueue('bia2music-crawler');
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('bia2music-page-' . $this->page))
                ->expireAfter(600)
                ->releaseAfter(120),
        ];
    }

    public function backoff(): array
    {
        return [
            60,
            180,
            300,
            600,
            900,
        ];
    }

    public function handle(Bia2MusicCrawlerService $crawlerService): void
    {
        try {
            Log::info('Bia2Music page job started.', [
                'page' => $this->page,
                'attempt' => $this->attempts(),
            ]);

            $dispatchedCount = $crawlerService->crawlPage($this->page);

            Log::info('Bia2Music page job finished.', [
                'page' => $this->page,
                'dispatched_count' => $dispatchedCount,
            ]);
        } catch (Throwable $exception) {
            if ($this->isTemporaryError($exception)) {
                $delay = random_int(5, 10);

                $crawlerService->activateBia2MusicCooldown($delay);

                Log::warning('Bia2Music page temporary error. Global cooldown activated and job released.', [
                    'page' => $this->page,
                    'attempt' => $this->attempts(),
                    'delay_seconds' => $delay,
                    'message' => $exception->getMessage(),
                ]);

                $this->release($delay + random_int(60, 300));

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
        Log::error('Bia2Music page job failed.', [
            'page' => $this->page,
            'attempt' => $this->attempts(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}
