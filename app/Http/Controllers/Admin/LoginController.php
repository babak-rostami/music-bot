<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check() && Auth::user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'email' => 'ایمیل یا رمز عبور اشتباه است.',
                ]);
        }

        $user = Auth::user();

        if (!$user->isAdmin()) {
            Auth::logout();
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'email' => 'شما دسترسی ادمین ندارید.',
                ]);
        }

        if (!$user->isActive()) {
            Auth::logout();
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'email' => 'حساب شما غیرفعال شده است.',
                ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
