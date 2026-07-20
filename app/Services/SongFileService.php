<?php

namespace App\Services;

use App\Models\Song;
use App\Models\SongDownloadLink;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SongFileService
{
    /**
     * لیست کیفیت‌ها به ترتیب اولویت (از بالا به پایین)
     */
    protected array $qualityPriority = ['320', '128'];

    /**
     * گرفتن فایل آیدی کش شده برای پلتفرم مشخص
     * اول کیفیت 320 رو چک می‌کنه، بعد 128
     *
     * @param  string  $platform  مثلا telegram یا bale
     * @return array{file_id: string, fileIdColumn: string, quality: string}|null
     */
    public function getCachedFileId(Song $song, string $platform): ?array
    {
        foreach ($this->qualityPriority as $quality) {
            $column = "{$platform}_file_id_{$quality}";

            // اگه ستون وجود نداشت، برو بعدی
            if (! array_key_exists($column, $song->getAttributes())) {
                continue;
            }

            $fileId = $song->{$column};

            if (! empty($fileId)) {
                return [
                    'file_id'      => $fileId,
                    'fileIdColumn' => $column,
                    'quality'      => $quality,
                ];
            }
        }

        return null;
    }

    /**
     * گرفتن بهترین لینک دانلود بر اساس کیفیت و اولویت درخواست‌های موفق
     * هر بار که یه لینک انتخاب میشه، اولویتش 1 دونه زیاد میشه
     *
     * @return SongDownloadLink|null
     */
    public function getBestDownloadLink(Song $song): ?SongDownloadLink
    {
        // سورت بر اساس کیفیت (320 اول) بعد اولویت (بیشترین اولویت اول)
        $links = $song->downloadLinks()
            ->get(['id', 'quality', 'download_url', 'priority'])
            ->sortBy([
                // کیفیت 320 اول بیاد، بعد 128
                ['quality', 'desc'],
                // بیشترین اولویت اول
                ['priority', 'desc'],
                // اگه اولویت برابر بود، بر اساس کیفیت به ترتیب priority آرایه
                fn($a, $b) => array_search($a->quality, $this->qualityPriority) <=> array_search($b->quality, $this->qualityPriority),
            ])
            ->values();

        if ($links->isEmpty()) {
            return null;
        }

        // اول 320 رو امتحان کن
        $link320 = $links->firstWhere('quality', '320');
        if ($link320) {
            if ($this->pingUrl($link320)) {
                $link320->increment('priority');
                return $link320;
            }
        }

        // اگه 320 جواب نداد، 128 رو امتحان کن
        $link128 = $links->firstWhere('quality', '128');
        if ($link128) {
            if ($this->pingUrl($link128)) {
                $link128->increment('priority');
                return $link128;
            }
        }

        return null;
    }

    /**
     * فقط یه درخواست سبک HEAD می‌زنه ببینه لینک زنده هست یا نه
     * فایل دانلود نمیشه
     */
    protected function pingUrl(SongDownloadLink $link): bool
    {
        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.5',
                ])
                ->timeout(5) // برای پینگ تایم‌اوت کم کافیه
                ->connectTimeout(3)
                ->head($link->download_url);

            return $response->successful(); // 200 تا 299
        } catch (\Throwable $e) {
            Log::warning('پینگ لینک دانلود شکست خورد', [
                'link_id' => $link->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
