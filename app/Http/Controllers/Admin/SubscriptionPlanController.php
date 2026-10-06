<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::withCount('subscriptions')->orderBy('sort_order')->get();
        return view('admin.subscription-plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.subscription-plans.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        SubscriptionPlan::create($validated);

        return redirect()->route('admin.subscription-plans.index')->with('success', 'پلن جدید ساخته شد.');
    }

    public function edit(SubscriptionPlan $subscriptionPlan)
    {
        return view('admin.subscription-plans.edit', ['plan' => $subscriptionPlan]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $validated = $this->validated($request);
        $subscriptionPlan->update($validated);

        return redirect()->route('admin.subscription-plans.index')->with('success', 'پلن ویرایش شد.');
    }

    public function destroy(SubscriptionPlan $subscriptionPlan)
    {
        // پرداخت‌های قبلی این پلن (به‌خاطر اسنپ‌شات) دست‌نخورده می‌مونن، فقط ارتباطشون با پلن قطع می‌شه
        $subscriptionPlan->delete();

        return back()->with('success', 'پلن حذف شد.');
    }

    public function toggleActive(SubscriptionPlan $subscriptionPlan)
    {
        $subscriptionPlan->update(['is_active' => !$subscriptionPlan->is_active]);
        return back()->with('success', 'وضعیت پلن تغییر کرد.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'                 => ['required', 'string', 'max:100'],
            'emoji'                => ['nullable', 'string', 'max:10'],
            'requests_per_window'  => ['required', 'integer', 'min:1'],
            'window_hours'         => ['required', 'integer', 'min:1'],
            'monthly_price_toman'  => ['required', 'integer', 'min:1000'],
            'sort_order'           => ['nullable', 'integer'],
            'is_active'            => ['nullable'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
