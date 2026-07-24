<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        if ($user->is_super_admin) {
            return $next($request);
        }

        abort_unless(app()->bound('tenant'), 403);

        $papel = app('tenant')->users()
            ->where('user_id', $user->id)
            ->value('papel');

        abort_unless($papel === 'admin', 403);

        return $next($request);
    }
}
