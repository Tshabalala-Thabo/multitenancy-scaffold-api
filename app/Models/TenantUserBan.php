<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUserBan extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'reason',
        'banned_at',
        'banned_by',
        'unbanned_at',
        'unbanned_by',
        'unban_reason',
    ];

    protected $casts = [
        'banned_at' => 'datetime',
        'unbanned_at' => 'datetime',
    ];


    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }

    public function unbannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unbanned_by');
    }
}
