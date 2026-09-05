<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Redirect each authenticated account to the route allowed by its role.
     * Fortify's static `home` value cannot express this distinction safely.
     */
    public function toResponse(mixed $request): RedirectResponse
    {
        $route = $request->user()->is_admin ? 'admin.dashboard' : 'user.dashboard';

        $intendedPath = parse_url((string) $request->session()->get('url.intended', ''), PHP_URL_PATH);
        $adminPath = parse_url(route('admin.home'), PHP_URL_PATH);

        if (! $request->user()->is_admin && is_string($intendedPath) && is_string($adminPath)
            && Str::is([$adminPath, $adminPath.'/*'], $intendedPath)) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended(route($route));
    }
}
