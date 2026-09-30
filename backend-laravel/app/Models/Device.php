<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A collector phone bound by its Keystore public key (spec §18.3). The server trusts
 * the registered key, never a device id the client reports.
 */
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory, HasPublicId;

    protected $fillable = [
        'user_id', 'platform', 'fingerprint_hash', 'public_key_pem', 'key_hardware_backed', 'attestation_ok',
        'last_integrity_verdict', 'model', 'manufacturer', 'os_version', 'app_version', 'status',
    ];

    protected $hidden = ['public_key_pem', 'fingerprint_hash', 'active_marker'];

    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'key_hardware_backed' => 'boolean',
            'attestation_ok' => 'boolean',
            'last_integrity_verdict' => 'array',
            'approved_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
