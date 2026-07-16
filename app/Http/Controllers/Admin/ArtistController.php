<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class ArtistController extends Controller
{
    /**
     * نمایش لیست خوانندگان همراه با قابلیت جستجو، مرتب‌سازی و آمار آهنگ‌ها
     */
    public function index(Request $request): View
    {
        $search = $request->input('search');
        $sortBy = $request->input('sort_by', 'songs_count');
        $sortDirection = $request->input('sort_direction', 'desc');
        $perPage = (int) $request->input('per_page', 15);

        // اعتبارسنجی ستون‌های مرتب‌سازی
        $allowedSorts = ['id', 'name', 'slug', 'songs_count', 'created_at'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'songs_count';
        }

        $allowedDirections = ['asc', 'desc'];
        if (!in_array($sortDirection, $allowedDirections)) {
            $sortDirection = 'desc';
        }

        // محاسبه آمار کلی برای نمایش در هدر صفحه
        $stats = [
            'total' => Artist::count(),
            'with_songs' => Artist::has('songs')->count(),
            'without_bio' => Artist::whereNull('bio')->orWhere('bio', '')->count(),
        ];

        // ساخت کوئری اصلی و محاسبه تعداد آهنگ‌های هر خواننده با withCount('songs')
        $query = Artist::query()
            ->withCount('songs')
            ->search($search);

        // اعمال مرتب‌سازی
        $query->orderBy($sortBy, $sortDirection);

        $artists = $query->paginate(in_array($perPage, [15, 30, 50, 100]) ? $perPage : 15)
            ->withQueryString();

        return view('admin.artists.index', compact('artists', 'stats', 'search', 'sortBy', 'sortDirection', 'perPage'));
    }

    /**
     * نمایش فرم افزودن خواننده جدید
     */
    public function create(): View
    {
        return view('admin.artists.create');
    }

    /**
     * ذخیره خواننده جدید در دیتابیس
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:artists,name',
            'slug' => 'nullable|string|max:255|unique:artists,slug',
            'bio'  => 'nullable|string',
        ], [
            'name.required' => 'نام خواننده الزامی است.',
            'name.unique'   => 'این نام خواننده قبلاً ثبت شده است.',
            'slug.unique'   => 'این اسلاگ (slug) قبلاً برای خواننده دیگری ثبت شده است.',
        ]);

        // اگر اسلاگ وارد نشده باشد، از روی نام به صورت خودکار (با پشتیبانی از حروف فارسی و انگلیسی) ساخته می‌شود
        if (empty($validated['slug'])) {
            // استفاده از Str::slug با پارامتر سوم null برای حفظ حروف فارسی
            $validated['slug'] = Str::slug($validated['name'], '-', null);
        }

        Artist::create($validated);

        return redirect()->route('admin.artists.index')
            ->with('success', "خواننده «{$validated['name']}» با موفقیت اضافه شد.");
    }

    /**
     * نمایش فرم ویرایش اطلاعات خواننده (نام و بیوگرافی)
     */
    public function edit(Artist $artist): View
    {
        return view('admin.artists.edit', compact('artist'));
    }

    /**
     * به‌روزرسانی اطلاعات خواننده (طبق خواسته شما اسلاگ در ویرایش تغییر نمی‌کند)
     */
    public function update(Request $request, Artist $artist): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:artists,name,' . $artist->id,
            'bio'  => 'nullable|string',
        ], [
            'name.required' => 'نام خواننده الزامی است.',
            'name.unique'   => 'این نام خواننده قبلاً ثبت شده است.',
        ]);

        // فقط نام و بیوگرافی آپدیت می‌شود و اسلاگ دست‌نخورده باقی می‌ماند
        $artist->update([
            'name' => $validated['name'],
            'bio'  => $validated['bio'],
        ]);

        return redirect()->route('admin.artists.index')
            ->with('success', "اطلاعات خواننده «{$artist->name}» با موفقیت به‌روزرسانی شد.");
    }

    /**
     * حذف خواننده از دیتابیس
     */
    public function destroy(Artist $artist): RedirectResponse
    {
        $name = $artist->name;

        // در صورت نیاز، روابط جدول واسط با آهنگ‌ها به طور خودکار توسط دیتابیس یا لاراول حذف می‌شود
        $artist->delete();

        return redirect()->route('admin.artists.index')
            ->with('success', "خواننده «{$name}» با موفقیت از سیستم حذف شد.");
    }
}
