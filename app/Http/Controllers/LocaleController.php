<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Language switch in the header (and on the login page for guests). */
class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $locale = (string) $request->input('locale');
        if (in_array($locale, User::LOCALES, true)) {
            $request->user()?->forceFill(['locale' => $locale])->save();
            $request->session()->put('locale', $locale);
        }

        return back();
    }
}
