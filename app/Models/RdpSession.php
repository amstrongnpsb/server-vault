<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RdpSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'server_id',
        'user_id',
        'connection_token',
        'status',
        'token_expires_at',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'token_expires_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
