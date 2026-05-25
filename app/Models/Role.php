<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Role extends SpatieRole
{
    use HasUuids;

    /**
     * The type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
}
