<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Writes audit-log entries. Sensitive keys are stripped from properties.
 */
class ActivityLogger
{
    private const REDACTED_KEYS = ['password', 'password_confirmation', 'current_password', 'remember_token', 'token'];

    public function __construct(private readonly ?Request $request = null) {}

    public function log(string $action, ?Model $subject = null, ?string $description = null, array $properties = [], ?int $userId = null): ActivityLog
    {
        $request = $this->request ?? request();

        return ActivityLog::create([
            'user_id' => $userId ?? $request?->user()?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $this->redact($properties) ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
        ]);
    }

    private function redact(array $properties): array
    {
        foreach ($properties as $key => $value) {
            if (in_array($key, self::REDACTED_KEYS, true)) {
                unset($properties[$key]);
            } elseif (is_array($value)) {
                $properties[$key] = $this->redact($value);
            }
        }

        return $properties;
    }
}
