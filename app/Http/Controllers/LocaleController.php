<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $locale = $request->string('locale')->toString();

        abort_unless(in_array($locale, config('app.supported_locales', []), true), 422);

        $request->session()->put('locale', $locale);

        return back();
    }
}
