<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Song;
use App\Models\SongDownloadLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SongDownloadLinkController extends Controller
{
    public function index($id)
    {
        $song = Song::findOrFail($id);

        $downloadLinks = $song->downloadLinks()
            ->orderBy('priority', 'asc')
            ->get();

        return view('admin.songs.download-links.index', compact('song', 'downloadLinks'));
    }

    public function update(Request $request, SongDownloadLink $downloadLink)
    {
        $validated = $request->validate([
            'provider'     => 'required|string|max:255',
            'quality'      => 'nullable|string|max:255',
            'download_url' => 'required|string|max:2048',
            'priority'     => 'required|integer|min:0',
        ]);

        $downloadLink->update($validated);

        return redirect()->back()->with('success', 'لینک دانلود با موفقیت ویرایش شد.');
    }

    public function destroy(SongDownloadLink $downloadLink)
    {
        $songId = $downloadLink->song_id;
        $downloadLink->delete();

        return redirect()->back()->with('success', 'لینک دانلود با موفقیت حذف شد.');
    }

    public function testLink(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url|max:2048',
        ]);

        $url = $request->input('url');
        $startTime = microtime(true);

        try {
            // HEAD request اول
            $headResponse = Http::withoutVerifying()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => '*/*',
                    'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.5',
                ])
                ->timeout(15)
                ->connectTimeout(10)
                ->head($url);

            $responseTime = round((microtime(true) - $startTime) * 1000) . ' ms';

            if ($headResponse->successful()) {
                return response()->json([
                    'success' => true,
                    'details' => [
                        'status_code' => $headResponse->status(),
                        'content_type' => $headResponse->header('Content-Type'),
                        'content_length' => $headResponse->header('Content-Length')
                            ? $this->formatBytes($headResponse->header('Content-Length'))
                            : 'نامشخص',
                        'response_time' => $responseTime,
                    ],
                ]);
            }

            // HEAD جواب نداد → GET با Range (فقط 1KB)
            $startTime2 = microtime(true);

            $getResponse = Http::withoutVerifying()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => '*/*',
                    'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.5',
                    'Range' => 'bytes=0-1023',
                ])
                ->timeout(15)
                ->connectTimeout(10)
                ->get($url);

            $responseTime = round((microtime(true) - $startTime2) * 1000) . ' ms';

            if ($getResponse->successful()) {
                return response()->json([
                    'success' => true,
                    'details' => [
                        'status_code' => $getResponse->status(),
                        'content_type' => $getResponse->header('Content-Type'),
                        'content_length' => $getResponse->header('Content-Range') ?: 'نامشخص',
                        'response_time' => $responseTime,
                    ],
                ]);
            }

            // هر دو ناموفق
            return response()->json([
                'success' => false,
                'error' => 'لینک در دسترس نیست',
                'details' => [
                    'status_code' => $getResponse->status(),
                    'message' => 'سرور مقصد با کد ' . $getResponse->status() . ' پاسخ داد.',
                    'type' => 'connection_error',
                ],
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000) . ' ms';

            // تشخیص نوع خطا
            $message = $e->getMessage();
            $type = 'connection_error';

            if (str_contains(strtolower($message), 'timeout') || str_contains(strtolower($message), 'timed out')) {
                $type = 'timeout_error';
            } elseif (str_contains(strtolower($message), 'could not resolve') || str_contains(strtolower($message), 'dns')) {
                $type = 'dns_error';
            } elseif (str_contains(strtolower($message), 'ssl') || str_contains(strtolower($message), 'certificate')) {
                $type = 'ssl_error';
            }

            return response()->json([
                'success' => false,
                'error' => 'اتصال به سرور مقصد برقرار نشد',
                'details' => [
                    'message' => $message,
                    'type' => $type,
                    'response_time' => $responseTime,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'خطای غیرمنتظره',
                'details' => [
                    'message' => $e->getMessage(),
                    'type' => 'unknown_error',
                ],
            ]);
        }
    }

    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
