<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        if (! $user->hasPermission($permission)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Forbidden. You do not have the required permission.'], 403);
            }

            abort(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
        }

        return $next($request);
    }
}
