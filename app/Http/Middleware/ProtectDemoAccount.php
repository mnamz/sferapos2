<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public demo login (shown on the login page) may use the whole POS, but
 * must not lock other people out: no user management, no profile/password
 * changes, no account deletion.
 */
class ProtectDemoAccount
{
    private const BLOCKED_ROUTES = [
        'users.store',
        'users.update',
        'users.destroy',
        'profile.update',
        'profile.destroy',
        'password.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $demoEmail = config('app.demo.email');

        if (config('app.demo.enabled')
            && $demoEmail
            && $request->user()?->email === $demoEmail
            && ! $request->isMethodSafe()
            && in_array($request->route()?->getName(), self::BLOCKED_ROUTES, true)) {
            return back()->with('error', 'This action is disabled for the demo account.');
        }

        return $next($request);
    }
}
