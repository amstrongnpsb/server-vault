<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class ServerDatabase extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are not mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be appended to the model's array form.
     *
     * @var array
     */
    protected $appends = ['has_credentials'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'server_id' => 'string',
            'port' => 'integer',
        ];
    }

    /**
     * Get the server that owns this database.
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Check if the database has credentials.
     */
    public function getHasCredentialsAttribute(): bool
    {
        return !empty($this->credentials);
    }

    /**
     * Get the decrypted credentials.
     */
    public function getDecryptedCredentialsAttribute(): ?string
    {
        if (empty($this->credentials)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->credentials);
        } catch (\Exception $e) {
            return $this->credentials;
        }
    }


}
