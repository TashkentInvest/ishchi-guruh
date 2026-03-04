<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Admins always pass
        if ($user->isAdmin()) {
            return $next($request);
        }

        if ($user->isPending()) {
            return redirect()->route('approval.pending');
        }

        if ($user->isRejected()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')
                ->withErrors(['login' => 'Afsuski, sizning so\'rovingiz rad etildi. Qo\'shimcha ma\'lumot uchun administratorga murojaat qiling.']);
        }

        return $next($request);
    }
}
