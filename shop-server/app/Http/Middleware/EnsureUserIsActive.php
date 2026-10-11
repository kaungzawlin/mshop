<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isBlocked()) {
            $user->tokens()->delete();

            return response()->json([
                'message' => 'This account has been blocked. Contact support.',
                'code' => 'account_blocked',
            ], 401);
        }

        return $next($request);
    }
}
