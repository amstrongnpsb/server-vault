<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;

class ServerPolicy
{
    public function create(User $user): bool
    {
        return $user->can('create servers');
    }

    public function connect(User $user, Server $server): bool
    {
        return $user->can('connect servers');
    }
}
