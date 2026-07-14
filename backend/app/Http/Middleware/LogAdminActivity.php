<?php

namespace App\Http\Middleware;

use App\Models\AdminActivityLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every state-changing admin-panel request into
 * admin_activity_logs. Sits on the protected admin route group, so any new
 * admin action is captured automatically — no per-controller wiring.
 */
class LogAdminActivity
{
    /** Request fields that must never be persisted. */
    private const SENSITIVE = ['password', 'password_confirmation', 'current_password', '_token', '_method'];

    private const MAX_VALUE_LENGTH = 500;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        try {
            $this->record($request, $response);
        } catch (\Throwable $e) {
            // The audit trail must never break the action it observes.
            Log::warning('admin activity logging failed', ['error' => $e->getMessage()]);
        }

        return $response;
    }

    private function record(Request $request, Response $response): void
    {
        [$subjectType, $subjectId] = $this->subject($request);

        AdminActivityLog::create([
            'admin_id' => Auth::guard('admin')->id(),
            'action' => $request->route()?->getName() ?? $request->path(),
            'method' => $request->method(),
            'path' => substr('/' . ltrim($request->path(), '/'), 0, 500),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payload' => $this->sanitizedPayload($request),
            'status' => $response->getStatusCode(),
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * The first route-bound model identifies what was acted on
     * (e.g. UserOnboarding #12).
     *
     * @return array{0: ?string, 1: ?int}
     */
    private function subject(Request $request): array
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                return [class_basename($parameter), $parameter->getKey()];
            }
        }

        return [null, null];
    }

    private function sanitizedPayload(Request $request): ?array
    {
        $input = $request->except(self::SENSITIVE);
        if ($input === []) {
            return null;
        }

        array_walk_recursive($input, function (&$value) {
            if (is_string($value) && strlen($value) > self::MAX_VALUE_LENGTH) {
                $value = substr($value, 0, self::MAX_VALUE_LENGTH) . '…';
            }
        });

        return $input;
    }
}
