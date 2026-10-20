<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Per-user language; guests get the session choice or their browser's. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $locale = $user instanceof User
            ? $user->preferredLocale()
            : ($request->session()->get('locale') ?? $request->getPreferredLanguage(User::LOCALES) ?? config('app.locale'));

        app()->setLocale(in_array($locale, User::LOCALES, true) ? $locale : (string) config('app.locale'));

        return $next($request);
    }
}
