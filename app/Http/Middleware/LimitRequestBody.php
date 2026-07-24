<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitRequestBody
{
    public function handle(Request $request, Closure $next, int $maxBytes = 1048576): Response
    {
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);

        if ($contentLength > $maxBytes || strlen($request->getContent()) > $maxBytes) {
            abort(413, 'Payload muito grande.');
        }

        return $next($request);
    }
}
