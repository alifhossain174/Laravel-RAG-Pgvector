<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotSuspended
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_suspended) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Your account is suspended.');
        }

        return redirect()
            ->route('account.suspended')
            ->with('error', 'Your account is suspended. Please contact support if you believe this is a mistake.');
    }
}
