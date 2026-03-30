<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    protected array $supportedLocales = ['zh-CN', 'en', 'ko'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language');
        
        if ($locale && in_array($locale, $this->supportedLocales)) {
            app()->setLocale($locale);
        } else {
            app()->setLocale(config('app.locale', 'zh-CN'));
        }

        return $next($request);
    }
}
