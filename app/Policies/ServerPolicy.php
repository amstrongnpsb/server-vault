<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;

class ServerPolicy
{
    public function connect(User $user, Server $server): bool
    {
        return $user->can('connect servers');
    }
}
