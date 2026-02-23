<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiLogger
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $this->log($request, $response, $duration);

        return $response;
    }

    private function log(Request $request, Response $response, float $duration): void
    {
        $user = $request->user();

        $payload = [
            'method'      => $request->method(),
            'url'         => $request->fullUrl(),
            'status'      => $response->getStatusCode(),
            'duration_ms' => $duration,
            'ip'          => $request->ip(),
            'user_id'     => $user?->id,
            'user_agent'  => $request->userAgent(),
        ];

        // Include request body for non-GET requests (redact sensitive fields)
        if (!$request->isMethod('GET')) {
            $body = $request->except([
                'password', 'password_confirmation', 'token',
                'otp', 'code', 'secret', 'authorization',
            ]);

            // Truncate large values
            array_walk($body, function (&$value) {
                if (is_string($value) && strlen($value) > 500) {
                    $value = substr($value, 0, 500) . '...[truncated]';
                }
            });

            $payload['body'] = $body;
        }

        // Log validation errors on 422
        if ($response->getStatusCode() === 422) {
            $content = json_decode($response->getContent(), true);
            $payload['errors'] = $content['errors'] ?? $content['message'] ?? null;
        }

        $level = match (true) {
            $response->getStatusCode() >= 500 => 'error',
            $response->getStatusCode() >= 400 => 'warning',
            default                           => 'info',
        };

        Log::channel('api')->{$level}('API Request', $payload);
    }
}
