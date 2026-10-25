<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets a harmless, readable "gt_editor=1" hint for logged-in admins, so site-text.js on
 * the public pages only asks the admin app for a session when an admin might be present.
 * It grants nothing by itself: every edit is checked against the real admin session.
 */
class EditorHintCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (auth()->guard('web')->check()) {
            $response->headers->setCookie(Cookie::create(
                'gt_editor', '1', now()->addHours(8), '/', null,
                str_starts_with((string) config('app.url'), 'https://'), false, false, Cookie::SAMESITE_LAX
            ));
        }

        return $response;
    }
}
