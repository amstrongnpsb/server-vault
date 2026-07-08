<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'host',
        'os',
        'status',
        'description',
        'port',
        'username',
        'credentials',
        'last_checked_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * Available OS options
     */
    public static function getOsOptions(): array
    {
        return ['Ubuntu', 'Debian', 'CentOS', 'Windows'];
    }

    /**
     * Available status options
     */
    public static function getStatusOptions(): array
    {
        return ['Online', 'Offline'];
    }

    /**
     * Scope a query to search servers by name or host.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, ?string $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('host', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Scope a query to filter servers by OS.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|array|null  $os
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterByOs($query, $os)
    {
        if (empty($os)) {
            return $query;
        }

        if (is_string($os)) {
            $os = [$os];
        }

        return $query->whereIn('os', $os);
    }

    /**
     * Scope a query to filter servers by status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|array|null  $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterByStatus($query, $status)
    {
        if (empty($status)) {
            return $query;
        }

        if (is_string($status)) {
            $status = [$status];
        }

        return $query->whereIn('status', $status);
    }

    /**
     * Check if the server is online
     */
    public function isOnline(): bool
    {
        return $this->status === 'Online';
    }

    /**
     * Get the OS icon class for display
     */
    public function getOsIcon(): string
    {
        return match ($this->os) {
            'Ubuntu' => 'ubuntu-icon',
            'Debian' => 'debian-icon',
            'CentOS' => 'centos-icon',
            'Windows' => 'windows-icon',
            default => 'server-icon',
        };
    }
}