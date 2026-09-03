<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
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

        return redirect()->intended(route($route));
    }
}
