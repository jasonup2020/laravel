<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 60): Response
    {
        $key = $this->resolveRequestSignature($request);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        RateLimiter::hit($key, 60); // 60 seconds decay

        $response = $next($request);

        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $maxAttempts - RateLimiter::attempts($key)));

        return $response;
    }

    /**
     * Resolve request signature.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        // Get the current route information
        $route = $request->route();
        $routeName = $route ? $route->getName() : '';
        $routeUri = $route ? $route->uri() : $request->path();
        $method = $request->method();

        // Create a unique key for each API endpoint
        $endpointKey = sha1($method . '|' . $routeUri . '|' . $routeName);

        // Use user ID if authenticated, otherwise use IP address
        if ($user = $request->user()) {
            return sha1($user->getAuthIdentifier() . '|' . $endpointKey);
        }

        return sha1($request->ip() . '|' . $endpointKey);
    }
}
