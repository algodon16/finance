<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginOtp extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'code_hash',
        'expires_at',
        'attempts',
        'consumed_at',
        'last_sent_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'last_sent_at' => 'datetime',
    ];

    protected $hidden = ['code_hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || now()->greaterThan($this->expires_at);
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isActive(): bool
    {
        return ! $this->isConsumed() && ! $this->isExpired();
    }
}
