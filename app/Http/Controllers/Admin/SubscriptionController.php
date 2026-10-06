<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotUser;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = Subscription::query()->latest();

        if ($platform = $request->input('platform')) {
            $query->where('platform', $platform);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        $subscriptions = $query->paginate(30)->withQueryString();

        $botUsers = BotUser::query()
            ->whereIn('platform', $subscriptions->pluck('platform')->unique())
            ->whereIn('user_id', $subscriptions->pluck('user_id')->unique())
            ->get()
            ->keyBy(fn($u) => $u->platform . ':' . $u->user_id);

        $stats = [
            'active_count'  => Subscription::where('status', 'active')->where('expires_at', '>', now())->count(),
            'expired_count' => Subscription::where('status', 'expired')->count(),
        ];

        return view('admin.subscriptions.index', compact('subscriptions', 'botUsers', 'stats', 'platform', 'status', 'userId'));
    }
}
