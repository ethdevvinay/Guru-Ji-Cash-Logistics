<?php

declare(strict_types=1);

namespace App\Services\Audit;

/**
 * Canonical form and hash of one audit row. JSON columns are decoded and re-encoded with
 * sorted keys and unescaped Unicode, so the hash does not depend on how the DB stored them.
 */
final class AuditHasher
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    private const FIELDS = [
        'id', 'occurred_at', 'actor_user_id', 'actor_role', 'actor_type', 'action', 'entity_type', 'entity_id',
        'before', 'after', 'ip', 'user_agent', 'device_id', 'request_id', 'meta',
    ];

    private const JSON_FIELDS = ['before', 'after', 'meta'];

    private const INT_FIELDS = ['id', 'actor_user_id', 'device_id'];

    /**
     * @param  array<string, mixed>  $row  a raw audit_logs row
     */
    public static function payload(array $row): string
    {
        $data = [];

        foreach (self::FIELDS as $field) {
            $value = $row[$field] ?? null;

            if ($value !== null && in_array($field, self::JSON_FIELDS, true)) {
                $value = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
            } elseif ($value !== null && in_array($field, self::INT_FIELDS, true)) {
                $value = (int) $value;
            } elseif ($value !== null) {
                $value = (string) $value;
            }

            $data[$field] = $value;
        }

        return json_encode(self::sortKeys($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    public static function hash(string $previousHash, string $payload): string
    {
        return hash('sha256', $previousHash."\n".$payload);
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::sortKeys(...), $value);
    }
}
