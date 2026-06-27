<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        if ($request->has('status') && $request->input('status') !== '') {
            $query->where('is_active', $request->boolean('status'));
        }

        $users = $query->latest()->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'username'  => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'full_name' => ['nullable', 'string', 'max:255'],
            'name'      => ['nullable', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone'     => ['nullable', 'string', 'max:20', Rule::unique('users')->ignore($user->id)],
            'bio'       => ['nullable', 'string', 'max:1000'],
            'role'      => ['required', 'in:admin,user'],
            'is_active' => ['nullable'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['name'] = $validated['full_name'] ?? $validated['username'];

        $user->update($validated);

        return redirect()->route('admin.users.index')->with('success', 'کاربر با موفقیت بروزرسانی شد.');
    }

    public function toggleActive(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'شما نمی‌توانید وضعیت حساب خودتان را تغییر دهید.');
        }

        $user->update(['is_active' => !$user->is_active]);

        $status = $user->is_active ? 'فعال' : 'غیرفعال';
        return back()->with('success', "حساب کاربری {$status} شد.");
    }

    public function changePassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        return back()->with('success', 'رمز عبور با موفقیت تغییر کرد.');
    }
}
