<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckIfBlocked
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->IsBlocked) {
            return response()->json([
                'message' => 'تم حظر حسابك من قبل الإدارة. السبب: '.($user->BlockReason ?? 'غير محدد'),
                'is_blocked' => true,
                'block_reason' => $user->BlockReason,
            ], 403);
        }

        return $next($request);
    }
}
