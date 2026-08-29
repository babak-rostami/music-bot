<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CommentController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('search');
        $status = $request->input('status');

        // فقط نظرات اصلی توی لیست نشون داده می‌شن؛ تعداد پاسخ‌ها روی خود نظر نمایش داده می‌شه
        $query = Comment::query()->with('song')->whereNull('parent_id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('body', 'LIKE', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $comments = $query->latest()->paginate(20)->withQueryString();

        $stats = [
            'total'    => Comment::count(),
            'approved' => Comment::where('status', 1)->count(),
            'hidden'   => Comment::where('status', 0)->count(),
            'replies'  => Comment::whereNotNull('parent_id')->count(),
        ];

        return view('admin.comments.index', compact('comments', 'stats', 'search', 'status'));
    }

    public function updateStatus(Request $request, Comment $comment): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:0,1']]);

        $comment->update(['status' => (int) $validated['status']]);

        if ($comment->parent_id) {
            Comment::where('id', $comment->parent_id)->update([
                'replies_count' => Comment::where('parent_id', $comment->parent_id)->where('status', 1)->count(),
            ]);
        }

        $label = $comment->status ? 'تایید' : 'مخفی';
        return redirect()->back()->with('success', "وضعیت نظر به «{$label}» تغییر کرد.");
    }

    public function update(Request $request, Comment $comment): RedirectResponse
    {
        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:100'],
            'body'   => ['required', 'string', 'max:1000'],
            'status' => ['required', 'in:0,1'],
        ]);

        $comment->update($validated);

        if ($comment->parent_id) {
            Comment::where('id', $comment->parent_id)->update([
                'replies_count' => Comment::where('parent_id', $comment->parent_id)->where('status', 1)->count(),
            ]);
        }

        return redirect()->back()->with('success', 'نظر با موفقیت ویرایش شد.');
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $parentId = $comment->parent_id;

        $comment->delete(); // cascadeOnDelete پاسخ‌های زیر این نظر رو هم پاک می‌کنه

        if ($parentId) {
            Comment::where('id', $parentId)->update([
                'replies_count' => Comment::where('parent_id', $parentId)->where('status', 1)->count(),
            ]);
        }

        return redirect()->back()->with('success', 'نظر حذف شد.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:comments,id'],
        ]);

        $count = Comment::whereIn('id', $validated['ids'])->delete();

        return redirect()->back()->with('success', "{$count} نظر حذف شد.");
    }
}
