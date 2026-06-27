<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('admin.login');
        }

        if (!Auth::user()->isAdmin()) {
            abort(403, 'شما دسترسی ادمین ندارید.');
        }

        if (!Auth::user()->isActive()) {
            Auth::logout();
            return redirect()->route('admin.login')->withErrors([
                'email' => 'حساب شما غیرفعال شده است.',
            ]);
        }

        return $next($request);
    }
}
