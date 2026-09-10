<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cookie = $request->cookie('appearance');
        $appearance = app(SettingsService::class)
            ->appearanceFrom($request->user(), is_string($cookie) ? $cookie : null)
            ->value;

        View::share('appearance', $appearance);

        return $next($request);
    }
}
