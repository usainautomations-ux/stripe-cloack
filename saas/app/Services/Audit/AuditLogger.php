<?php

namespace App\Services\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

/**
 * Append-only record of who did what.
 *
 * Writes go through the query builder rather than an Eloquent model on purpose:
 * there is no update path and no delete path, so there is nothing for a later
 * refactor to accidentally expose. An audit trail that the application can edit
 * is not an audit trail.
 */
class AuditLogger
{
    /**
     * Keys that must never be written to the log, at any nesting level.
     * Belt-and-braces against a caller passing a whole request payload in.
     */
    private const REDACTED_KEYS = [
        'password', 'password_confirmation', 'secret', 'secret_key', 'site_secret',
        'setup_token', 'token', 'signature', 'authorization', 'card', 'cvc', 'number',
    ];

    public function record(string $action, ?Model $entity = null, array $metadata = [], string $actorType = 'user'): void
    {
        $user = Auth::user();

        DB::table('audit_logs')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $user?->tenant_id ?? app(\App\Support\TenantContext::class)->tenantId(),
            'actor_user_id' => $user?->id,
            'actor_type' => $actorType,
            'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'ip_address' => Request::ip(),
            'user_agent' => Str::limit((string) Request::userAgent(), 250, ''),
            'metadata' => json_encode($this->redact($metadata)),
            'created_at' => now(),
        ]);
    }

    /**
     * Replace anything that looks like a credential with a marker, recursively.
     */
    private function redact(array $metadata): array
    {
        foreach ($metadata as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::REDACTED_KEYS, true)) {
                $metadata[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $metadata[$key] = $this->redact($value);
            }
        }

        return $metadata;
    }
}
